<?php

declare(strict_types=1);
use RoundlyConsulting\Campaigns\Jobs\SendCampaignEmail;
use RoundlyConsulting\Campaigns\Managers\InMemoryManager;

return [
    'manager' => InMemoryManager::class,

    'batch-queue' => env('CAMPAIGNS_BATCH_QUEUE', 'default'),

    'sending-queue' => env('CAMPAIGNS_SENDING_QUEUE', 'default'),

    'process-recipient-job' => SendCampaignEmail::class,
];
