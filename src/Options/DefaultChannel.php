<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Options;

use RoundlyConsulting\Campaigns\Support\CampaignsConfig;
use RoundlyConsulting\Options\BaseOption;

/**
 * Notification channel used by SendCampaignNotification's on-demand notifiable.
 * Falls back to the campaigns.notification-channel config value when unset.
 */
final class DefaultChannel extends BaseOption
{
    public function castAs(): string
    {
        return 'string';
    }

    public function default(): string
    {
        return CampaignsConfig::notificationChannel();
    }
}
