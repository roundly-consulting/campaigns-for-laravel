<?php

declare(strict_types=1);

use RoundlyConsulting\Campaigns\Jobs\SendCampaignEmail;
use RoundlyConsulting\Campaigns\Managers\InMemoryManager;

return [
    /*
     * The Manager implementation bound in the container. Defaults to the
     * storage-agnostic InMemoryManager. Switch to DatabaseManager::class to
     * persist campaigns (and run the published migrations) without writing one.
     */
    'manager' => env('CAMPAIGNS_MANAGER', InMemoryManager::class),

    /*
     * Queue used for the campaign batch.
     */
    'batch-queue' => env('CAMPAIGNS_BATCH_QUEUE', 'default'),

    /*
     * Queue used for each per-recipient processing job.
     */
    'sending-queue' => env('CAMPAIGNS_SENDING_QUEUE', 'default'),

    /*
     * The job dispatched once per recipient. Must implement
     * RoundlyConsulting\Campaigns\Contracts\ProcessesCampaignRecipient.
     */
    'process-recipient-job' => SendCampaignEmail::class,

    /*
     * Notification class delivered by SendCampaignNotification. It is
     * constructed with the Campaign and CampaignRecipient. Required only when
     * process-recipient-job is set to SendCampaignNotification.
     */
    'notification' => env('CAMPAIGNS_NOTIFICATION'),

    /*
     * Routing channel used by SendCampaignNotification's on-demand notifiable.
     */
    'notification-channel' => env('CAMPAIGNS_NOTIFICATION_CHANNEL', 'mail'),
];
