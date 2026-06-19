<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Jobs\SendCampaignNotification;
use RoundlyConsulting\Campaigns\Managers\Manager;
use RoundlyConsulting\Campaigns\Notifications\CampaignNotification;

/**
 * Test double notification the host app would normally supply.
 */
final class TestCampaignNotification extends CampaignNotification
{
    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->line($this->campaign->content);
    }
}

beforeEach(function (): void {
    $this->manager = resolve(Manager::class);

    config()->set('campaigns.notification', TestCampaignNotification::class);

    $this->job = new SendCampaignNotification(
        campaign: new Campaign(
            uuid: 'd58284c9-4e49-4c07-a26c-d220ce62b5ec',
            subject: 'Test Campaign',
            content: 'Hello',
            fromName: 'Unit Testing',
            fromAddress: 'unit@testing.tld',
        ),
        recipient: new CampaignRecipient(
            uuid: '629b33c5-8160-436b-bb33-02866471cfa6',
            name: 'Jane Doe',
            reachableAt: 'jane@doe.tld',
        )
    );
});

it('does not notify when the batch is cancelled', function (): void {
    $this->job->withFakeBatch(cancelledAt: CarbonImmutable::now());

    NotificationFacade::fake();

    $this->job->handle($this->manager);

    NotificationFacade::assertNothingSent();
});

it('sends a notification and marks the recipient processed', function (): void {
    $this->job->withFakeBatch();

    NotificationFacade::fake();

    $this->job->handle($this->manager);

    NotificationFacade::assertSentOnDemand(
        TestCampaignNotification::class,
        function (TestCampaignNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool {
            return $notifiable->routes['mail'] === 'jane@doe.tld';
        }
    );

    expect($this->job->recipient->hasBeenProcessed)->toBeTrue();
});

it('marks the recipient failed when no notification class is configured', function (): void {
    config()->set('campaigns.notification', null);

    $this->job->withFakeBatch();

    NotificationFacade::fake();

    $this->job->handle($this->manager);

    expect($this->job->recipient)
        ->errorOccured->toBeTrue()
        ->errorMessage->toContain('campaigns.notification');
});
