<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Events;

use RoundlyConsulting\Campaigns\Campaign;

final class CampaignCancelled
{
    public function __construct(
        public readonly Campaign $campaign,
    ) {}
}
