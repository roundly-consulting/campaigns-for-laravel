<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Contracts;

use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Managers\Manager;

/**
 * Contract every per-recipient processing job must satisfy. A job is constructed
 * with the campaign and the recipient it should deliver to, then handled with the
 * configured manager so it can mark the recipient processed or failed.
 */
interface ProcessesCampaignRecipient
{
    public function __construct(Campaign $campaign, CampaignRecipient $recipient);

    public function handle(Manager $manager): void;
}
