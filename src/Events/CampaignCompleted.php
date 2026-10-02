<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Events;

use RoundlyConsulting\Campaigns\Campaign;

/**
 * Every delivery job of the campaign ran (or it had nobody to send to). Some deliveries may
 * have failed — see `$campaign->progress->failed`.
 */
final class CampaignCompleted
{
    public function __construct(
        public readonly Campaign $campaign,
    ) {}
}
