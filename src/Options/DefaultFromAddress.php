<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Options;

use RoundlyConsulting\Campaigns\Support\CampaignsConfig;
use RoundlyConsulting\Options\BaseOption;

/**
 * Default sender address used when a campaign is dispatched without ->from().
 * Falls back to the campaigns.from-address config value when unset.
 */
final class DefaultFromAddress extends BaseOption
{
    public function castAs(): string
    {
        return 'string';
    }

    public function default(): string
    {
        return CampaignsConfig::fromAddress();
    }
}
