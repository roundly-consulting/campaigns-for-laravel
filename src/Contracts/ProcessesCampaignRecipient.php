<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Contracts;

use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignManager;
use RoundlyConsulting\Campaigns\CampaignRecipient;

/**
 * Contract every per-recipient processing job must satisfy. A job is constructed with the
 * campaign and the recipient it should deliver to, then handled with the campaigns manager
 * so it can record the outcome:
 *
 *     $campaigns->campaign($this->campaign)->markProcessed($this->recipient);
 *     $campaigns->campaign($this->campaign)->markFailed($this->recipient, $error);
 */
interface ProcessesCampaignRecipient
{
    public function __construct(Campaign $campaign, CampaignRecipient $recipient);

    public function handle(CampaignManager $campaigns): void;
}
