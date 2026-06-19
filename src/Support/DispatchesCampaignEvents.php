<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Support;

use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Events\CampaignCancelled;
use RoundlyConsulting\Campaigns\Events\CampaignCompleted;
use RoundlyConsulting\Campaigns\Events\CampaignFailed;
use RoundlyConsulting\Campaigns\Events\CampaignPrepared;
use RoundlyConsulting\Campaigns\Events\CampaignStarted;

trait DispatchesCampaignEvents
{
    private function dispatchStatusEvent(Campaign $campaign, CampaignStatus $status): void
    {
        $event = match ($status) {
            CampaignStatus::Pending => new CampaignPrepared($campaign),
            CampaignStatus::Processing => new CampaignStarted($campaign),
            CampaignStatus::Completed => new CampaignCompleted($campaign),
            CampaignStatus::Failed => new CampaignFailed($campaign),
            CampaignStatus::Canceled => new CampaignCancelled($campaign),
            CampaignStatus::Created => null,
        };

        if ($event !== null) {
            event($event);
        }
    }
}
