<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Exceptions;

use RoundlyConsulting\Campaigns\Campaign;

/**
 * The campaign is not in a state that allows the operation — for example starting one that
 * is already sending, which would queue every recipient a second time.
 */
final class InvalidCampaignTransition extends CampaignException
{
    public static function cannotStart(Campaign $campaign): self
    {
        return new self(
            "Campaign [{$campaign->uuid}] is {$campaign->progress->status->value}; only a Pending (prepared) campaign can start."
        );
    }
}
