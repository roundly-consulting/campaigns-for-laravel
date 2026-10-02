<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Stores;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignProgress;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Contracts\CampaignStore;
use RoundlyConsulting\Campaigns\Models\CampaignRecipientRecord;
use RoundlyConsulting\Campaigns\Models\CampaignRecord;

/**
 * Persists campaigns in the `campaigns` / `campaign_recipients` tables (publish and run the
 * `campaigns-migrations` first). Behaves exactly like InMemoryCampaignStore — one contract
 * suite runs against both.
 *
 * A non-uuid can never match a `uuid` column, so every lookup by one resolves to "nothing"
 * without a query. `uuid` is a real type on Postgres and plain text on SQLite, so without the
 * guard the same miss raised `invalid input syntax for type uuid` on Postgres instead of
 * returning null.
 *
 * Recipients are keyed by (campaign, uuid), exactly like the in-memory store: one recipient
 * saved under two campaigns is two rows, never one moved between them.
 *
 * Writes include soft-deleted rows: the keys are unique, so an upsert that skipped a trashed
 * row would try to insert a duplicate.
 */
final class DatabaseCampaignStore implements CampaignStore
{
    public function find(string $campaignUuid): ?Campaign
    {
        if (! Str::isUuid($campaignUuid)) {
            return null;
        }

        $record = CampaignRecord::query()->where('uuid', $campaignUuid)->first();

        return $record instanceof CampaignRecord ? $this->toCampaign($record) : null;
    }

    public function all(int $offset = 0, int $limit = 10): Collection
    {
        return CampaignRecord::query()
            ->orderBy('id')
            ->offset(max($offset, 0))
            ->limit(max($limit, 0))
            ->get()
            ->map(fn (CampaignRecord $record): Campaign => $this->toCampaign($record))
            ->values()
            ->toBase();
    }

    public function save(Campaign $campaign): void
    {
        CampaignRecord::withTrashed()->updateOrCreate(
            ['uuid' => $campaign->uuid],
            [
                'subject' => $campaign->subject,
                'content' => $campaign->content,
                'from_name' => $campaign->fromName,
                'from_address' => $campaign->fromAddress,
                'status' => $campaign->progress->status,
                'sent' => $campaign->progress->sent,
                'pending' => $campaign->progress->pending,
                'total' => $campaign->progress->total,
                'batch' => $campaign->batch,
                'started_at' => $campaign->startedAt,
                'ended_at' => $campaign->endedAt,
            ],
        );
    }

    public function saveRecipients(string $campaignUuid, array $recipients): void
    {
        foreach ($recipients as $recipient) {
            CampaignRecipientRecord::withTrashed()->updateOrCreate(
                ['campaign_uuid' => $campaignUuid, 'uuid' => $recipient->uuid],
                [
                    'name' => $recipient->name,
                    'reachable_at' => $recipient->reachableAt,
                    'has_been_processed' => $recipient->hasBeenProcessed,
                    'error_occured' => $recipient->errorOccured,
                    'error_message' => $recipient->errorMessage,
                ],
            );
        }
    }

    public function recipients(string $campaignUuid, int $offset = 0, ?int $limit = null): Collection
    {
        if (! Str::isUuid($campaignUuid)) {
            return collect();
        }

        return CampaignRecipientRecord::query()
            ->where('campaign_uuid', $campaignUuid)
            ->orderBy('id')
            ->offset(max($offset, 0))
            // SQLite rejects an OFFSET without a LIMIT, so "all from here" is the largest one.
            ->limit($limit === null ? PHP_INT_MAX : max($limit, 0))
            ->get()
            ->map(fn (CampaignRecipientRecord $record): CampaignRecipient => $this->toRecipient($record))
            ->values()
            ->toBase();
    }

    public function findRecipient(string $campaignUuid, string $recipientUuid): ?CampaignRecipient
    {
        if (! Str::isUuid($campaignUuid) || ! Str::isUuid($recipientUuid)) {
            return null;
        }

        $record = CampaignRecipientRecord::query()
            ->where('campaign_uuid', $campaignUuid)
            ->where('uuid', $recipientUuid)
            ->first();

        return $record instanceof CampaignRecipientRecord ? $this->toRecipient($record) : null;
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
            campaignUuid: $record->campaign_uuid,
        );
    }
}
