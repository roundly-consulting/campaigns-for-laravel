<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Events;

use RoundlyConsulting\Campaigns\Campaign;

/**
 * Every delivery job of the campaign ran and not one delivery got through (at least one
 * failed). A campaign where only some deliveries failed completes instead.
 */
final class CampaignFailed
{
    public function __construct(
        public readonly Campaign $campaign,
    ) {}
}
