<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Campaigns\Actions\ChangeCampaignStatusAction;
use RoundlyConsulting\Campaigns\Actions\StartCampaignAction;
use RoundlyConsulting\Campaigns\Contracts\CampaignStore;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Events\CampaignCancelled;
use RoundlyConsulting\Campaigns\Events\CampaignCompleted;
use RoundlyConsulting\Campaigns\Events\CampaignStarted;
use RoundlyConsulting\Campaigns\Exceptions\InvalidCampaignTransition;
use RoundlyConsulting\Campaigns\Facades\Campaigns;
use RoundlyConsulting\Campaigns\Tests\Fixtures\InterleavingStore;

/**
 * Two processes start the same Pending campaign (a double-clicked admin button, two scheduler
 * workers). The second one starts inside the first one's read-check-write window — before its
 * status check, or between reading Pending and writing Processing — and exactly one of them
 * may queue the recipients.
 */
beforeEach(fn () => fakeBus());

it('queues every recipient once when two starts race', function (string $store, int $at): void {
    useStore($store);

    $campaign = Campaigns::create('Subject', 'Body')->from('shop@shop.tld')->to(['a@a.tld', 'b@b.tld'])->prepare();

    app()->instance(CampaignStore::class, new InterleavingStore(
        inner: app(CampaignStore::class),
        at: $at,
        interleave: fn () => app(StartCampaignAction::class)->execute($campaign->uuid),
    ));

    Event::fake([CampaignStarted::class]);

    expect(fn () => Campaigns::start($campaign->uuid))->toThrow(InvalidCampaignTransition::class)
        ->and(Campaigns::campaign($campaign)->batch()->added)->toHaveCount(2)
        ->and(Campaigns::find($campaign->uuid)->progress->status)->toBe(CampaignStatus::Processing);

    Event::assertDispatchedTimes(CampaignStarted::class, 1);
})->with('stores')->with([
    'before the status check' => 1,
    'between reading Pending and writing Processing' => 2,
]);

it('drops a transition another process won, without its event', function (string $store): void {
    useStore($store);

    $campaign = Campaigns::create('Subject', 'Body')->from('shop@shop.tld')->to('a@a.tld')->dispatch();

    app()->instance(CampaignStore::class, new InterleavingStore(
        inner: app(CampaignStore::class),
        at: 1,
        interleave: fn () => app(ChangeCampaignStatusAction::class)->execute($campaign, CampaignStatus::Completed),
    ));

    Event::fake([CampaignCancelled::class, CampaignCompleted::class]);

    expect(app(ChangeCampaignStatusAction::class)->execute($campaign, CampaignStatus::Canceled)->progress->status)
        ->toBe(CampaignStatus::Completed)
        ->and(Campaigns::find($campaign->uuid)->progress->status)->toBe(CampaignStatus::Completed);

    Event::assertDispatchedTimes(CampaignCompleted::class, 1);
    Event::assertNotDispatched(CampaignCancelled::class);
})->with('stores');
