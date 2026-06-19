<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Managers;

use Closure;
use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignProgress;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Events\RecipientFailed;
use RoundlyConsulting\Campaigns\Events\RecipientProcessed;
use RoundlyConsulting\Campaigns\Exceptions\CampaignNotFound;
use RoundlyConsulting\Campaigns\Jobs\SendCampaignEmail;
use RoundlyConsulting\Campaigns\Models\CampaignRecipientRecord;
use RoundlyConsulting\Campaigns\Models\CampaignRecord;
use RoundlyConsulting\Campaigns\Support\DispatchesCampaignEvents;

final class DatabaseManager implements Manager
{
    use DispatchesCampaignEvents;

    public function find(string $campaignUuid): ?Campaign
    {
        $record = CampaignRecord::query()->where('uuid', $campaignUuid)->first();

        return $record instanceof CampaignRecord ? $this->toCampaign($record) : null;
    }

    public function findOrFail(string $campaignUuid): Campaign
    {
        return $this->find($campaignUuid) ?? throw CampaignNotFound::withUuid($campaignUuid);
    }

    public function onEachCampaign(Closure $callback, int $offset = 0, int $limit = 10): void
    {
        CampaignRecord::query()
            ->orderBy('id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->each(fn (CampaignRecord $record) => $callback($this->toCampaign($record)));
    }

    public function prepare(Campaign $campaign): void
    {
        $record = CampaignRecord::query()->updateOrCreate(
            ['uuid' => $campaign->uuid],
            [
                'subject' => $campaign->subject,
                'content' => $campaign->content,
                'from_name' => $campaign->fromName,
                'from_address' => $campaign->fromAddress,
                'status' => $campaign->progress->status,
            ],
        );

        $batch = Bus::batch([])
            ->onQueue(
                config('campaigns.batch-queue', 'default')
            )
            ->finally(fn () => $this->changeCampaignStatus($campaign->uuid, CampaignStatus::Completed))
            ->catch(fn () => $this->changeCampaignStatus($campaign->uuid, CampaignStatus::Failed))
            ->allowFailures()
            ->dispatch();

        $record->batch = $batch->id;
        $record->save();

        $campaign->batch = $batch->id;

        $this->changeCampaignStatus($campaign->uuid, CampaignStatus::Pending);
    }

    public function start(string $campaignUuid): void
    {
        $campaign = $this->findOrFail($campaignUuid);

        $this->changeCampaignStatus($campaignUuid, CampaignStatus::Processing);

        /** @var class-string $job */
        $job = config('campaigns.process-recipient-job', SendCampaignEmail::class);

        $campaign = $this->findOrFail($campaignUuid);

        $jobs = CampaignRecipientRecord::query()
            ->where('campaign_uuid', $campaignUuid)
            ->get()
            ->map(fn (CampaignRecipientRecord $record) => new $job($campaign, $this->toRecipient($record)))
            ->all();

        $this->findBatchForCampaign($campaign)?->add($jobs);
    }

    /**
     * @param  list<CampaignRecipient>  $recipients
     */
    public function pushRecipientsToCampaign(string $campaignUuid, array $recipients): void
    {
        foreach ($recipients as $recipient) {
            CampaignRecipientRecord::query()->updateOrCreate(
                ['uuid' => $recipient->uuid],
                [
                    'campaign_uuid' => $campaignUuid,
                    'name' => $recipient->name,
                    'reachable_at' => $recipient->reachableAt,
                    'has_been_processed' => $recipient->hasBeenProcessed,
                    'error_occured' => $recipient->errorOccured,
                    'error_message' => $recipient->errorMessage,
                ],
            );
        }
    }

    public function cancel(string $campaignUuid): void
    {
        $campaign = $this->findOrFail($campaignUuid);

        $this->findBatchForCampaign($campaign)?->cancel();

        $this->changeCampaignStatus($campaignUuid, CampaignStatus::Canceled);
    }

    public function markRecipientAsProcessed(Campaign $campaign, CampaignRecipient $recipient): void
    {
        $recipient->hasBeenProcessed = true;

        CampaignRecipientRecord::query()
            ->where('uuid', $recipient->uuid)
            ->update([
                'has_been_processed' => true,
                'error_occured' => false,
                'error_message' => null,
            ]);

        event(new RecipientProcessed($campaign, $recipient));
    }

    public function markRecipientAsFailed(Campaign $campaign, CampaignRecipient $recipient, string $error): void
    {
        $recipient->hasBeenProcessed = false;
        $recipient->errorOccured = true;
        $recipient->errorMessage = $error;

        CampaignRecipientRecord::query()
            ->where('uuid', $recipient->uuid)
            ->update([
                'has_been_processed' => false,
                'error_occured' => true,
                'error_message' => $error,
            ]);

        event(new RecipientFailed($campaign, $recipient, $error));
    }

    public function findRecipient(string $campaignUuid, string $recipientUuid): ?CampaignRecipient
    {
        $record = CampaignRecipientRecord::query()
            ->where('campaign_uuid', $campaignUuid)
            ->where('uuid', $recipientUuid)
            ->first();

        return $record instanceof CampaignRecipientRecord ? $this->toRecipient($record) : null;
    }

    public function findBatchForCampaign(Campaign $campaign): ?Batch
    {
        /** @var BatchRepository $batches */
        $batches = resolve(BatchRepository::class);

        if ($campaign->batch === null) {
            return null;
        }

        return $batches->find($campaign->batch);
    }

    private function changeCampaignStatus(string $campaignUuid, CampaignStatus $status): void
    {
        $record = CampaignRecord::query()->where('uuid', $campaignUuid)->first();

        if (! $record instanceof CampaignRecord) {
            return;
        }

        if ($record->status === $status) {
            return;
        }

        $record->status = $status;

        if ($status === CampaignStatus::Processing && $record->started_at === null) {
            $record->started_at = Carbon::now();
        }

        if ($status->isTerminal() && $record->ended_at === null) {
            $record->ended_at = Carbon::now();
        }

        $this->syncBatchProgress($record);

        $record->save();

        $this->dispatchStatusEvent($this->toCampaign($record), $status);
    }

    private function syncBatchProgress(CampaignRecord $record): void
    {
        if ($record->batch === null) {
            return;
        }

        /** @var BatchRepository $batches */
        $batches = resolve(BatchRepository::class);

        $batch = $batches->find($record->batch);

        if ($batch instanceof Batch) {
            $record->total = $batch->totalJobs;
            $record->sent = $batch->processedJobs();
            $record->pending = $batch->pendingJobs;
        }
    }

    private function toCampaign(CampaignRecord $record): Campaign
    {
        return new Campaign(
            uuid: $record->uuid,
            subject: $record->subject,
            content: $record->content,
            fromName: $record->from_name,
            fromAddress: $record->from_address,
            progress: new CampaignProgress(
                status: $record->status,
                sent: $record->sent,
                pending: $record->pending,
                total: $record->total,
            ),
            startedAt: $record->started_at !== null ? Carbon::instance($record->started_at) : null,
            endedAt: $record->ended_at !== null ? Carbon::instance($record->ended_at) : null,
            batch: $record->batch,
        );
    }

    private function toRecipient(CampaignRecipientRecord $record): CampaignRecipient
    {
        return new CampaignRecipient(
            uuid: $record->uuid,
            name: $record->name,
            reachableAt: $record->reachable_at,
            hasBeenProcessed: $record->has_been_processed,
            errorOccured: $record->error_occured,
            errorMessage: $record->error_message,
        );
    }
}
