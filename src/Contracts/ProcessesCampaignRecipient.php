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
 *
 * Record the outcome, or let the job fail: a job that fails outright (throws, times out, runs
 * out of attempts) counts as a failed delivery, while the campaign's other jobs keep running.
 * Do not do both, or the recipient is counted as failed twice. Check
 * `$this->batch()?->cancelled()` first so a cancelled campaign stops sending.
 */
interface ProcessesCampaignRecipient
{
    public function __construct(Campaign $campaign, CampaignRecipient $recipient);

    public function handle(CampaignManager $campaigns): void;
}
