<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Tests\Fixtures;

use Closure;
use Illuminate\Support\Collection;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Contracts\CampaignStore;
use RoundlyConsulting\Campaigns\DataTransferObjects\RecipientCounts;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;

/**
 * A store that lets a test run "another process" at an exact point of a lifecycle action:
 * after the `$at`-th read of a campaign, `$interleave` runs once, and the caller still gets
 * the snapshot it read before — the stale copy a racing process would hold.
 */
final class InterleavingStore implements CampaignStore
{
    private int $finds = 0;

    private bool $running = false;

    public function __construct(
        private readonly CampaignStore $inner,
        private readonly int $at,
        private readonly Closure $interleave,
    ) {}

    public function find(string $campaignUuid): ?Campaign
    {
        $campaign = $this->inner->find($campaignUuid);

        if (! $this->running && ++$this->finds === $this->at) {
            $this->running = true;
            ($this->interleave)();
        }

        return $campaign;
    }

    public function all(int $offset = 0, int $limit = 10): Collection
    {
        return $this->inner->all($offset, $limit);
    }

    public function insert(Campaign $campaign): bool
    {
        return $this->inner->insert($campaign);
    }

    public function save(Campaign $campaign): void
    {
        $this->inner->save($campaign);
    }

    public function saveIfStatus(Campaign $campaign, CampaignStatus $expected): bool
    {
        return $this->inner->saveIfStatus($campaign, $expected);
    }

    public function saveRecipients(string $campaignUuid, array $recipients): void
    {
        $this->inner->saveRecipients($campaignUuid, $recipients);
    }

    public function recipients(string $campaignUuid, int $offset = 0, ?int $limit = null): Collection
    {
        return $this->inner->recipients($campaignUuid, $offset, $limit);
    }

    public function findRecipient(string $campaignUuid, string $recipientUuid): ?CampaignRecipient
    {
        return $this->inner->findRecipient($campaignUuid, $recipientUuid);
    }

    public function countRecipients(string $campaignUuid): RecipientCounts
    {
        return $this->inner->countRecipients($campaignUuid);
    }
}
