<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Contracts;

use Illuminate\Support\Collection;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;

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
     * Insert or update the campaign by its uuid.
     */
    public function save(Campaign $campaign): void;

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
}
