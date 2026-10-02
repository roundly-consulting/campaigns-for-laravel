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
use RoundlyConsulting\Campaigns\Facades\Campaigns;

beforeEach(function (): void {
    fakeBus();

    $this->campaign = new Campaign(
        uuid: '3b2d9cc2-a069-4284-a894-1c021c3bfcbb',
        subject: 'Events',
        content: 'Hello',
        fromName: 'Testing',
        fromAddress: 'test@testing.tld',
    );
});

afterEach(fn () => Carbon::setTestNow());

it('dispatches lifecycle events on real transitions', function (string $store): void {
    useStore($store);
    Event::fake();

    Campaigns::prepare($this->campaign, [new CampaignRecipient(uuid: '00000000-0000-4000-8000-00000000a001', name: 'Jane', reachableAt: 'jane@doe.tld')]);
    Event::assertDispatched(CampaignPrepared::class, fn (CampaignPrepared $event): bool => $event->campaign->progress->status === CampaignStatus::Pending);

    Campaigns::start($this->campaign->uuid);
    Event::assertDispatched(CampaignStarted::class);

    Campaigns::cancel($this->campaign->uuid);
    Event::assertDispatched(CampaignCancelled::class);
})->with('stores');

it('fires CampaignPrepared once the recipients are in place', function (): void {
    $seen = null;
    Event::listen(CampaignPrepared::class, function (CampaignPrepared $event) use (&$seen): void {
        $seen = Campaigns::campaign($event->campaign)->recipients()->count();
    });

    Campaigns::create('Subject', 'Body')->to(['a@a.tld', 'b@b.tld'])->prepare();

    expect($seen)->toBe(2);
});

it('sets startedAt when processing and endedAt on a terminal state', function (string $store): void {
    useStore($store);
    Carbon::setTestNow('2026-06-20 12:00:00');

    Campaigns::prepare($this->campaign);

    expect(Campaigns::find($this->campaign->uuid))
        ->startedAt->toBeNull()
        ->endedAt->toBeNull();

    Campaigns::start($this->campaign->uuid);

    expect(Campaigns::find($this->campaign->uuid)->startedAt?->toDateTimeString())
        ->toBe('2026-06-20 12:00:00');

    Campaigns::cancel($this->campaign->uuid);

    expect(Campaigns::find($this->campaign->uuid)->endedAt?->toDateTimeString())
        ->toBe('2026-06-20 12:00:00');
})->with('stores');

it('does not re-dispatch when the campaign already ended', function (): void {
    Campaigns::prepare($this->campaign);
    Campaigns::cancel($this->campaign->uuid);

    Event::fake();

    Campaigns::cancel($this->campaign->uuid);

    Event::assertNotDispatched(CampaignCancelled::class);
});

it('completes the campaign and dispatches completed on the batch finally callback', function (string $store): void {
    useStore($store);
    Carbon::setTestNow('2026-06-20 12:00:00');

    Campaigns::prepare($this->campaign);
    $batch = Campaigns::campaign($this->campaign->uuid)->batch();

    Event::fake();

    finishBatch($batch);

    Event::assertDispatched(CampaignCompleted::class);

    expect(Campaigns::find($this->campaign->uuid))
        ->progress->status->toBe(CampaignStatus::Completed)
        ->endedAt->not->toBeNull();
})->with('stores');

it('fails the campaign when its batch finishes with no delivery through', function (string $store): void {
    useStore($store);

    $campaign = Campaigns::prepare($this->campaign, [new CampaignRecipient(uuid: '00000000-0000-4000-8000-00000000a001', name: 'Jane', reachableAt: 'jane@doe.tld')]);
    Campaigns::start($campaign);
    Campaigns::campaign($campaign)->markFailed(Campaigns::campaign($campaign)->recipient('00000000-0000-4000-8000-00000000a001'), 'bounced');

    Event::fake();

    finishBatch(Campaigns::campaign($campaign)->batch());

    Event::assertDispatched(CampaignFailed::class);
    Event::assertNotDispatched(CampaignCompleted::class);

    expect(Campaigns::find($this->campaign->uuid)->progress)
        ->status->toBe(CampaignStatus::Failed)
        ->failed->toBe(1)
        ->sent->toBe(0);
})->with('stores');

it('completes the campaign when its batch finishes with some deliveries through', function (string $store): void {
    useStore($store);

    $campaign = Campaigns::prepare($this->campaign, [
        new CampaignRecipient(uuid: '00000000-0000-4000-8000-00000000a001', name: 'Jane', reachableAt: 'jane@doe.tld'),
        new CampaignRecipient(uuid: '00000000-0000-4000-8000-00000000a002', name: 'John', reachableAt: 'john@doe.tld'),
    ]);
    Campaigns::start($campaign);
    Campaigns::campaign($campaign)->markFailed(Campaigns::campaign($campaign)->recipient('00000000-0000-4000-8000-00000000a001'), 'bounced');
    Campaigns::campaign($campaign)->markProcessed(Campaigns::campaign($campaign)->recipient('00000000-0000-4000-8000-00000000a002'));

    Event::fake();

    finishBatch(Campaigns::campaign($campaign)->batch());

    Event::assertDispatched(CampaignCompleted::class);

    expect(Campaigns::find($this->campaign->uuid)->progress)
        ->status->toBe(CampaignStatus::Completed)
        ->sent->toBe(1)
        ->failed->toBe(1)
        ->percentage()->toBe(50.0);
})->with('stores');

it('dispatches recipient events with the correct payload', function (): void {
    $campaign = Campaigns::prepare($this->campaign, [
        new CampaignRecipient(uuid: '00000000-0000-4000-8000-00000000a001', name: 'Jane', reachableAt: 'jane@doe.tld'),
    ]);
    $recipient = Campaigns::campaign($campaign)->recipient('00000000-0000-4000-8000-00000000a001');

    Event::fake();

    Campaigns::campaign($campaign)->markProcessed($recipient);
    Event::assertDispatched(
        RecipientProcessed::class,
        fn (RecipientProcessed $event): bool => $event->recipient->uuid === '00000000-0000-4000-8000-00000000a001'
            && $event->campaign->uuid === $campaign->uuid
    );

    Campaigns::campaign($campaign)->markFailed($recipient, 'boom');
    Event::assertDispatched(
        RecipientFailed::class,
        fn (RecipientFailed $event): bool => $event->error === 'boom'
    );
});
