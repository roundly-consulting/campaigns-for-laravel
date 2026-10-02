<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Contracts;

use Illuminate\Support\Collection;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\DataTransferObjects\RecipientCounts;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;

/**
 * Where campaigns and their recipients are kept. Persistence only: the lifecycle (batches,
 * status transitions, events) lives in the actions, so an implementation only has to read
 * and write. Bound from `campaigns.store`; the package ships InMemoryCampaignStore (default)
 * and DatabaseCampaignStore.
 *
 * Reads return copies: changing a returned Campaign or CampaignRecipient changes nothing
 * until it is saved again.
 */
interface CampaignStore
{
    public function find(string $campaignUuid): ?Campaign;

    /**
     * Campaigns in creation order.
     *
     * @return Collection<int, Campaign>
     */
    public function all(int $offset = 0, int $limit = 10): Collection;

    /**
     * Insert a new campaign. Returns false — and writes nothing — when its uuid is already
     * taken (by a live or a soft-deleted campaign), so preparing can never overwrite one.
     * The check and the write are one atomic step: of two inserts racing on one uuid, one wins.
     */
    public function insert(Campaign $campaign): bool;

    /**
     * Insert or update the campaign by its uuid.
     */
    public function save(Campaign $campaign): void;

    /**
     * Save the campaign only while its stored status is still `$expected` — the atomic
     * compare-and-set every status transition goes through, so of two processes racing one
     * transition (a double-clicked start, a cancel against the batch finishing) exactly one
     * wins. Returns whether it was written; false for a campaign the store does not hold.
     */
    public function saveIfStatus(Campaign $campaign, CampaignStatus $expected): bool;

    /**
     * Insert or update each recipient under the given campaign, keyed by (campaign, uuid): the
     * same recipient saved under two campaigns is kept in both, never moved.
     *
     * @param  list<CampaignRecipient>  $recipients
     */
    public function saveRecipients(string $campaignUuid, array $recipients): void;

    /**
     * The campaign's recipients in the order they were added. A null limit returns them all.
     *
     * @return Collection<int, CampaignRecipient>
     */
    public function recipients(string $campaignUuid, int $offset = 0, ?int $limit = null): Collection;

    /**
     * A recipient of THIS campaign; a recipient of any other campaign reads as null.
     */
    public function findRecipient(string $campaignUuid, string $recipientUuid): ?CampaignRecipient;

    /**
     * The campaign's recipients counted by outcome — what its progress is built from.
     */
    public function countRecipients(string $campaignUuid): RecipientCounts;
}
