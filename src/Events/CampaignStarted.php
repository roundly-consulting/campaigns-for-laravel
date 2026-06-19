<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Events;

use RoundlyConsulting\Campaigns\Campaign;

final class CampaignStarted
{
    public function __construct(
        public readonly Campaign $campaign,
    ) {}
}
