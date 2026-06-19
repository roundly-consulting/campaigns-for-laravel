<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Notifications;

use Illuminate\Notifications\Notification;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;

/**
 * Base notification for campaign delivery via SendCampaignNotification.
 *
 * Host apps extend this and implement their own channel methods (toMail,
 * toVonage, toFcm, …). The campaign and recipient are injected so the host can
 * render channel-specific content.
 */
abstract class CampaignNotification extends Notification
{
    public function __construct(
        public readonly Campaign $campaign,
        public readonly CampaignRecipient $recipient,
    ) {}
}
