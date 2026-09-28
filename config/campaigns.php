<?php

declare(strict_types=1);

use RoundlyConsulting\Campaigns\Jobs\SendCampaignEmail;
use RoundlyConsulting\Campaigns\Stores\InMemoryCampaignStore;

return [
    /*
     * Where campaigns and recipients are kept: a Contracts\CampaignStore
     * implementation. Defaults to the in-process InMemoryCampaignStore. Switch
     * to Stores\DatabaseCampaignStore::class to persist campaigns (publish and
     * run the migrations first), or point at your own implementation.
     */
    'store' => env('CAMPAIGNS_STORE', InMemoryCampaignStore::class),

    /*
     * Default sender used when a campaign is dispatched without calling
     * ->from(). These seed the DefaultFromName / DefaultFromAddress options
     * (options override, config is the fallback). Leave blank to keep the
     * mailer's globally configured "from" address.
     */
    'from-name' => env('CAMPAIGNS_FROM_NAME', ''),
    'from-address' => env('CAMPAIGNS_FROM_ADDRESS', ''),

    /*
     * Recipient-resolution defaults applied when passing contact records or
     * HasContacts owners to ->to(). Seed the OnlyVerifiedRecipients and
     * DefaultRecipientContactType options.
     */
    'recipients' => [
        // Skip owners/contacts without a verified contact of the send kind.
        'only-verified' => env('CAMPAIGNS_ONLY_VERIFIED', false),

        // Contact kind resolved for an owner when ->viaContactType() is unset
        // (one of the RoundlyConsulting\Contacts\Enums\ContactType values).
        'contact-type' => env('CAMPAIGNS_RECIPIENT_CONTACT_TYPE', 'email'),
    ],

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
