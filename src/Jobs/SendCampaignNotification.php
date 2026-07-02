<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Contracts\ProcessesCampaignRecipient;
use RoundlyConsulting\Campaigns\Exceptions\CampaignException;
use RoundlyConsulting\Campaigns\Managers\Manager;
use RoundlyConsulting\Campaigns\Notifications\CampaignNotification;
use RoundlyConsulting\Campaigns\Support\CampaignSettings;
use Throwable;

/**
 * Delivers a campaign to a recipient through a host-configured Notification class.
 *
 * The notification class is read from `campaigns.notification` and is constructed
 * with the campaign so it can render channel-specific content. The recipient is
 * resolved to an on-demand notifiable routed on `campaigns.notification-channel`.
 */
final class SendCampaignNotification implements ProcessesCampaignRecipient, ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Campaign $campaign, public CampaignRecipient $recipient)
    {
        $this->queue = app(CampaignSettings::class)->sendingQueue();
    }

    public function handle(Manager $manager): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        try {
            $channel = app(CampaignSettings::class)->notificationChannel();

            NotificationFacade::route($channel, $this->recipient->reachableAt)
                ->notify($this->resolveNotification());

            $manager->markRecipientAsProcessed($this->campaign, $this->recipient);
        } catch (Throwable $e) {
            $manager->markRecipientAsFailed($this->campaign, $this->recipient, $e->getMessage());
        }
    }

    private function resolveNotification(): CampaignNotification
    {
        /** @var class-string<CampaignNotification>|null $class */
        $class = config('campaigns.notification');

        if ($class === null) {
            throw new CampaignException(
                'Set the [campaigns.notification] config to a CampaignNotification class before using SendCampaignNotification.'
            );
        }

        return new $class($this->campaign, $this->recipient);
    }
}
