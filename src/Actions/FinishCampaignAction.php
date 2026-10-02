<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Actions;

use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\Contracts\CampaignStore;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Support\CampaignBatches;

/**
 * @internal what a campaign's batch runs once every delivery job ran — and StartCampaignAction
 * for a campaign with nobody to send to
 *
 * The campaign ends Completed (CampaignCompleted), or Failed (CampaignFailed) when not one
 * delivery got through and at least one failed. Failed deliveries alone never end a campaign
 * early: until every job ran it stays Processing, and cancellable. A campaign that already
 * ended (a cancelled one) is left as it is.
 */
final readonly class FinishCampaignAction
{
    public function __construct(
        private CampaignStore $store,
        private CampaignBatches $batches,
        private ChangeCampaignStatusAction $changeStatus,
    ) {}

    public function execute(Campaign $campaign): Campaign
    {
        $progress = $this->batches->syncProgress($this->store->find($campaign->uuid) ?? clone $campaign, $this->store)->progress;

        $status = $progress->sent === 0 && $progress->failed > 0
            ? CampaignStatus::Failed
            : CampaignStatus::Completed;

        return $this->changeStatus->execute($campaign, $status);
    }
}
