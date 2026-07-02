<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Managers;

use Closure;
use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Events\RecipientFailed;
use RoundlyConsulting\Campaigns\Events\RecipientProcessed;
use RoundlyConsulting\Campaigns\Exceptions\CampaignNotFound;
use RoundlyConsulting\Campaigns\Jobs\SendCampaignEmail;
use RoundlyConsulting\Campaigns\Support\CampaignSettings;
use RoundlyConsulting\Campaigns\Support\DispatchesCampaignEvents;

final class InMemoryManager implements Manager
{
    use DispatchesCampaignEvents;

    /** @var array<string, Campaign> */
    public static array $campaigns = [];

    /** @var array<string, array<string, CampaignRecipient>> */
    public static array $recipients = [];

    public static function flush(): void
    {
        self::$campaigns = [];
        self::$recipients = [];
    }

    public function addCampaign(Campaign $campaign): self
    {
        self::$campaigns[$campaign->uuid] = $campaign;

        return $this;
    }

    public function find(string $campaignUuid): ?Campaign
    {
        return self::$campaigns[$campaignUuid] ?? null;
    }

    public function findOrFail(string $campaignUuid): Campaign
    {
        return $this->find($campaignUuid) ?? throw CampaignNotFound::withUuid($campaignUuid);
    }

    public function onEachCampaign(Closure $callback, int $offset = 0, int $limit = 10): void
    {
        $limit = $offset + min($limit, count(self::$campaigns));

        $campaigns = array_values(self::$campaigns);

        for ($i = $offset; $i < $limit; $i++) {
            if (isset($campaigns[$i])) {
                $callback($campaigns[$i]);
            }
        }
    }

    public function prepare(Campaign $campaign): void
    {
        $batch = Bus::batch([])
            ->onQueue(
                app(CampaignSettings::class)->batchQueue()
            )
            ->finally(fn () => $this->changeCampaignStatus($campaign, CampaignStatus::Completed))
            ->catch(fn () => $this->changeCampaignStatus($campaign, CampaignStatus::Failed))
            ->allowFailures()
            ->dispatch();

        $campaign->batch = $batch->id;

        $this->changeCampaignStatus($campaign, CampaignStatus::Pending);
    }

    public function start(string $campaignUuid): void
    {
        $campaign = $this->findOrFail($campaignUuid);

        $this->changeCampaignStatus($campaign, CampaignStatus::Processing);

        /** @var class-string $job */
        $job = config('campaigns.process-recipient-job', SendCampaignEmail::class);

        $jobs = collect(self::$recipients[$campaignUuid] ?? [])
            ->map(fn (CampaignRecipient $recipient) => new $job($campaign, $recipient));

        $this->findBatchForCampaign($campaign)?->add($jobs->toArray());
    }

    /**
     * @param  list<CampaignRecipient>  $recipients
     */
    public function pushRecipientsToCampaign(string $campaignUuid, array $recipients): void
    {
        self::$recipients[$campaignUuid] = array_merge(
            self::$recipients[$campaignUuid] ?? [],
            array_combine(
                keys: array_map(fn (CampaignRecipient $recipient) => $recipient->uuid, $recipients),
                values: $recipients,
            ),
        );
    }

    public function cancel(string $campaignUuid): void
    {
        $campaign = $this->findOrFail($campaignUuid);

        $this->findBatchForCampaign($campaign)?->cancel();

        $this->changeCampaignStatus($campaign, CampaignStatus::Canceled);
    }

    public function markRecipientAsProcessed(Campaign $campaign, CampaignRecipient $recipient): void
    {
        $recipient->hasBeenProcessed = true;

        self::$recipients[$campaign->uuid][$recipient->uuid] = $recipient;

        event(new RecipientProcessed($campaign, $recipient));
    }

    public function markRecipientAsFailed(Campaign $campaign, CampaignRecipient $recipient, string $error): void
    {
        $recipient->hasBeenProcessed = false;
        $recipient->errorOccured = true;
        $recipient->errorMessage = $error;

        self::$recipients[$campaign->uuid][$recipient->uuid] = $recipient;

        event(new RecipientFailed($campaign, $recipient, $error));
    }

    public function findRecipient(string $campaignUuid, string $recipientUuid): ?CampaignRecipient
    {
        return self::$recipients[$campaignUuid][$recipientUuid] ?? null;
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

    private function changeCampaignStatus(Campaign $campaign, CampaignStatus $status): void
    {
        if ($campaign->progress->status !== $status) {
            $campaign->progress->status = $status;

            if ($status === CampaignStatus::Processing && $campaign->startedAt === null) {
                $campaign->startedAt = Carbon::now();
            }

            if ($status->isTerminal() && $campaign->endedAt === null) {
                $campaign->endedAt = Carbon::now();
            }

            $this->campaignModified($campaign);

            $this->dispatchStatusEvent($campaign, $status);
        }
    }

    private function campaignModified(Campaign $campaign): void
    {
        if ($campaign->batch !== null) {
            $batch = $this->findBatchForCampaign($campaign);

            if ($batch instanceof Batch) {
                $campaign->progress->total = $batch->totalJobs;
                $campaign->progress->sent = $batch->processedJobs();
                $campaign->progress->pending = $batch->pendingJobs;
            }
        }

        self::$campaigns[$campaign->uuid] = $campaign;
    }
}
