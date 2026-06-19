<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Events\CampaignCancelled;
use RoundlyConsulting\Campaigns\Events\CampaignCompleted;
use RoundlyConsulting\Campaigns\Events\CampaignFailed;
use RoundlyConsulting\Campaigns\Events\CampaignPrepared;
use RoundlyConsulting\Campaigns\Events\CampaignStarted;
use RoundlyConsulting\Campaigns\Events\RecipientFailed;
use RoundlyConsulting\Campaigns\Events\RecipientProcessed;
use RoundlyConsulting\Campaigns\Managers\Manager;

beforeEach(function (): void {
    $this->manager = resolve(Manager::class);

    $this->campaign = new Campaign(
        uuid: '3b2d9cc2-a069-4284-a894-1c021c3bfcbb',
        subject: 'Events',
        content: 'Hello',
        fromName: 'Testing',
        fromAddress: 'test@testing.tld',
    );
});

it('dispatches lifecycle events on real transitions', function (): void {
    fakeBus();
    Event::fake();

    $this->manager->prepare($this->campaign);
    Event::assertDispatched(CampaignPrepared::class);

    $this->manager->start($this->campaign->uuid);
    Event::assertDispatched(CampaignStarted::class);

    $this->manager->cancel($this->campaign->uuid);
    Event::assertDispatched(CampaignCancelled::class);
});

it('sets startedAt when processing and endedAt on a terminal state', function (): void {
    fakeBus();
    Carbon::setTestNow('2026-06-20 12:00:00');

    $this->manager->prepare($this->campaign);

    expect($this->manager->find($this->campaign->uuid))
        ->startedAt->toBeNull()
        ->endedAt->toBeNull();

    $this->manager->start($this->campaign->uuid);

    expect($this->manager->find($this->campaign->uuid)->startedAt?->toDateTimeString())
        ->toBe('2026-06-20 12:00:00');

    $this->manager->cancel($this->campaign->uuid);

    expect($this->manager->find($this->campaign->uuid)->endedAt?->toDateTimeString())
        ->toBe('2026-06-20 12:00:00');

    Carbon::setTestNow();
});

it('does not re-dispatch when the status is unchanged', function (): void {
    fakeBus();

    $this->manager->addCampaign($this->campaign);
    $this->campaign->progress->status = CampaignStatus::Canceled;
    $this->campaign->endedAt = Carbon::now();

    Event::fake();

    $this->manager->cancel($this->campaign->uuid);

    Event::assertNotDispatched(CampaignCancelled::class);
});

it('completes the campaign and dispatches completed on the batch finally callback', function (): void {
    fakeBus();
    Carbon::setTestNow('2026-06-20 12:00:00');

    $this->manager->prepare($this->campaign);
    $batch = $this->manager->findBatchForCampaign($this->campaign);

    Event::fake();

    foreach ($batch->options['finally'] as $callback) {
        $callback($batch);
    }

    Event::assertDispatched(CampaignCompleted::class);

    expect($this->manager->find($this->campaign->uuid))
        ->progress->status->toBe(CampaignStatus::Completed)
        ->endedAt->not->toBeNull();

    Carbon::setTestNow();
});

it('fails the campaign and dispatches failed on the batch catch callback', function (): void {
    fakeBus();

    $this->manager->prepare($this->campaign);
    $batch = $this->manager->findBatchForCampaign($this->campaign);

    Event::fake();

    foreach ($batch->options['catch'] as $callback) {
        $callback($batch, new RuntimeException('boom'));
    }

    Event::assertDispatched(CampaignFailed::class);

    expect($this->manager->find($this->campaign->uuid))
        ->progress->status->toBe(CampaignStatus::Failed);
});

it('dispatches recipient events with the correct payload', function (): void {
    Event::fake();

    $recipient = new CampaignRecipient(
        uuid: 'r-1',
        name: 'Jane',
        reachableAt: 'jane@doe.tld',
    );

    $this->manager->markRecipientAsProcessed($this->campaign, $recipient);
    Event::assertDispatched(
        RecipientProcessed::class,
        fn (RecipientProcessed $event): bool => $event->recipient->uuid === 'r-1'
    );

    $this->manager->markRecipientAsFailed($this->campaign, $recipient, 'boom');
    Event::assertDispatched(
        RecipientFailed::class,
        fn (RecipientFailed $event): bool => $event->error === 'boom'
    );
});
