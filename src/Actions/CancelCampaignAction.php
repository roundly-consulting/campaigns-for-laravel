<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Actions;

use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\Contracts\CampaignStore;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Exceptions\CampaignNotFound;
use RoundlyConsulting\Campaigns\Support\CampaignBatches;

/**
 * Cancel a campaign: cancel its batch (queued jobs skip their delivery) and move it to
 * Canceled (CampaignCancelled). A campaign that already ended is returned unchanged.
 */
final readonly class CancelCampaignAction
{
    public function __construct(
        private CampaignStore $store,
        private ChangeCampaignStatusAction $changeStatus,
        private CampaignBatches $batches,
    ) {}

    /**
     * @throws CampaignNotFound
     */
    public function execute(Campaign|string $campaign): Campaign
    {
        $uuid = $campaign instanceof Campaign ? $campaign->uuid : $campaign;
        $campaign = $this->store->find($uuid) ?? throw CampaignNotFound::withUuid($uuid);

        if ($campaign->progress->status->isTerminal()) {
            return $campaign;
        }

        $this->batches->find($campaign)?->cancel();

        return $this->changeStatus->execute($campaign, CampaignStatus::Canceled);
    }
}
