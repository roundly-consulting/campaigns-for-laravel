<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Options;

use RoundlyConsulting\Campaigns\Support\CampaignsConfig;
use RoundlyConsulting\Options\BaseOption;

/**
 * Default sender name used when a campaign is dispatched without ->from().
 * Falls back to the campaigns.from-name config value when unset.
 */
final class DefaultFromName extends BaseOption
{
    public function castAs(): string
    {
        return 'string';
    }

    public function default(): string
    {
        return CampaignsConfig::fromName();
    }
}
