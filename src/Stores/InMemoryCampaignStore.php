<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Stores;

use Illuminate\Support\Collection;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Contracts\CampaignStore;

/**
 * Keeps campaigns in this process's memory — the default store. It needs no database and
 * is ideal for tests and create-and-send flows; nothing survives the process, so a queue
 * worker in another process starts empty (deliveries and events still happen there).
 */
final class InMemoryCampaignStore implements CampaignStore
{
    /** @var array<string, Campaign> */
    private array $campaigns = [];

    /** @var array<string, array<string, CampaignRecipient>> */
    private array $recipients = [];

    public function find(string $campaignUuid): ?Campaign
    {
        $campaign = $this->campaigns[$campaignUuid] ?? null;

        return $campaign === null ? null : clone $campaign;
    }

    public function all(int $offset = 0, int $limit = 10): Collection
    {
        return collect(array_slice(array_values($this->campaigns), max($offset, 0), max($limit, 0)))
            ->map(static fn (Campaign $campaign): Campaign => clone $campaign)
            ->values();
    }

    public function insert(Campaign $campaign): bool
    {
        if (isset($this->campaigns[$campaign->uuid])) {
            return false;
        }

        $this->save($campaign);

        return true;
    }

    public function save(Campaign $campaign): void
    {
        $this->campaigns[$campaign->uuid] = clone $campaign;
    }

    public function saveRecipients(string $campaignUuid, array $recipients): void
    {
        foreach ($recipients as $recipient) {
            $stored = clone $recipient;
            $stored->campaignUuid = $campaignUuid;

            $this->recipients[$campaignUuid][$recipient->uuid] = $stored;
        }
    }

    public function recipients(string $campaignUuid, int $offset = 0, ?int $limit = null): Collection
    {
        return collect(array_slice(array_values($this->recipients[$campaignUuid] ?? []), max($offset, 0), $limit === null ? null : max($limit, 0)))
            ->map(static fn (CampaignRecipient $recipient): CampaignRecipient => clone $recipient)
            ->values();
    }

    public function findRecipient(string $campaignUuid, string $recipientUuid): ?CampaignRecipient
    {
        $recipient = $this->recipients[$campaignUuid][$recipientUuid] ?? null;

        return $recipient === null ? null : clone $recipient;
    }
}
