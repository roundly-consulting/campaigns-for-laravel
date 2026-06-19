<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Events;

use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;

final class RecipientFailed
{
    public function __construct(
        public readonly Campaign $campaign,
        public readonly CampaignRecipient $recipient,
        public readonly string $error,
    ) {}
}
