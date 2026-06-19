<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Managers;

use Closure;
use Illuminate\Bus\Batch;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Exceptions\CampaignNotFound;

interface Manager
{
    public function find(string $campaignUuid): ?Campaign;

    /**
     * @throws CampaignNotFound
     */
    public function findOrFail(string $campaignUuid): Campaign;

    public function findRecipient(string $campaignUuid, string $recipientUuid): ?CampaignRecipient;

    public function findBatchForCampaign(Campaign $campaign): ?Batch;

    public function onEachCampaign(Closure $callback, int $offset = 0, int $limit = 10): void;

    public function prepare(Campaign $campaign): void;

    public function start(string $campaignUuid): void;

    /**
     * @param  list<CampaignRecipient>  $recipients
     */
    public function pushRecipientsToCampaign(string $campaignUuid, array $recipients): void;

    public function cancel(string $campaignUuid): void;

    public function markRecipientAsProcessed(Campaign $campaign, CampaignRecipient $recipient): void;

    public function markRecipientAsFailed(Campaign $campaign, CampaignRecipient $recipient, string $error): void;
}
