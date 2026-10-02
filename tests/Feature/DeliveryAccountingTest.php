<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Events\CampaignCompleted;
use RoundlyConsulting\Campaigns\Events\CampaignFailed;
use RoundlyConsulting\Campaigns\Facades\Campaigns;
use RoundlyConsulting\Campaigns\Stores\DatabaseCampaignStore;
use RoundlyConsulting\Campaigns\Tests\Fixtures\CampaignOwner;
use RoundlyConsulting\Campaigns\Tests\Fixtures\ThrowingRecipientJob;

/**
 * How deliveries are counted and how a campaign ends, run through a real database queue and
 * `queue:work`: a failed delivery is never counted as sent, one failing job neither ends the
 * campaign nor blocks cancelling it, and a campaign with nobody to send to still completes.
 */
describe('through a worker', function (): void {
    beforeEach(function (): void {
        useStore(DatabaseCampaignStore::class);
        useDatabaseQueue();
    });

    it('keeps sending, and stays cancellable, after one delivery job throws', function (): void {
        config()->set('campaigns.process-recipient-job', ThrowingRecipientJob::class);

        $campaign = Campaigns::create('Subject', 'Body')->from('shop@shop.tld')->to(['throw@a.tld', 'b@b.tld', 'c@c.tld'])->dispatch();

        workQueue(jobs: 1);

        expect(Campaigns::find($campaign->uuid)->progress)
            ->status->toBe(CampaignStatus::Processing)
            ->sent->toBe(0)
            ->failed->toBe(1);

        expect(Campaigns::cancel($campaign->uuid)->progress->status)->toBe(CampaignStatus::Canceled);

        workQueue();

        expect(Campaigns::find($campaign->uuid)->progress)
            ->status->toBe(CampaignStatus::Canceled)
            ->sent->toBe(0)
            ->and(Campaigns::campaign($campaign)->recipients()->filter(fn (CampaignRecipient $recipient): bool => $recipient->hasBeenProcessed))
            ->toHaveCount(0);
    });

    it('completes once every job ran, counting the thrown delivery as failed', function (): void {
        config()->set('campaigns.process-recipient-job', ThrowingRecipientJob::class);
        Event::fake([CampaignCompleted::class, CampaignFailed::class]);

        $campaign = Campaigns::create('Subject', 'Body')->from('shop@shop.tld')->to(['throw@a.tld', 'b@b.tld', 'c@c.tld'])->dispatch();

        workQueue();

        expect(Campaigns::find($campaign->uuid)->progress)
            ->status->toBe(CampaignStatus::Completed)
            ->total->toBe(3)
            ->sent->toBe(2)
            ->failed->toBe(1)
            ->remaining()->toBe(0);

        Event::assertDispatchedTimes(CampaignCompleted::class, 1);
        Event::assertNotDispatched(CampaignFailed::class);
    });

    it('fails a campaign none of whose deliveries got through', function (): void {
        config()->set('campaigns.process-recipient-job', ThrowingRecipientJob::class);
        Event::fake([CampaignCompleted::class, CampaignFailed::class]);

        $campaign = Campaigns::create('Subject', 'Body')->from('shop@shop.tld')->to(['throw@a.tld', 'throw@b.tld'])->dispatch();

        workQueue();

        expect(Campaigns::find($campaign->uuid)->progress)
            ->status->toBe(CampaignStatus::Failed)
            ->sent->toBe(0)
            ->failed->toBe(2);

        Event::assertDispatchedTimes(CampaignFailed::class, 1);
        Event::assertNotDispatched(CampaignCompleted::class);
    });

    it('never counts a delivery the job recorded as failed as sent', function (): void {
        $campaign = Campaigns::create('Subject', 'Body')->from('shop@shop.tld')->to(['not an address', 'ok@x.tld'])->dispatch();

        workQueue();

        expect(Campaigns::find($campaign->uuid)->progress)
            ->status->toBe(CampaignStatus::Completed)
            ->total->toBe(2)
            ->sent->toBe(1)
            ->failed->toBe(1)
            ->percentage()->toBe(50.0)
            ->and(Campaigns::campaign($campaign)->recipients()->first())
            ->errorOccured->toBeTrue();
    });

    it('completes a campaign with no recipients at once', function (): void {
        Event::fake([CampaignCompleted::class]);

        $campaign = Campaigns::create('Subject', 'Body')->from('shop@shop.tld')->to([])->dispatch();

        expect($campaign->progress->status)->toBe(CampaignStatus::Completed)
            ->and(Campaigns::find($campaign->uuid)->progress)
            ->status->toBe(CampaignStatus::Completed)
            ->total->toBe(0)
            ->isRunning()->toBeFalse();

        Event::assertDispatchedTimes(CampaignCompleted::class, 1);
    });
});

it('completes a campaign whose every owner was filtered out', function (string $store): void {
    fakeBus();
    useStore($store);

    $owner = CampaignOwner::create(['name' => 'Ada']);
    $owner->addEmail('ada@calc.test', primary: true);

    $campaign = Campaigns::create('Subject', 'Body')->onlyVerified()->to($owner)->dispatch();

    expect(Campaigns::find($campaign->uuid)->progress)
        ->status->toBe(CampaignStatus::Completed)
        ->isRunning()->toBeFalse()
        ->and($campaign->endedAt)->not->toBeNull();
})->with('stores');
