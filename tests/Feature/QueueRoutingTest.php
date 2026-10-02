<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Campaigns\Facades\Campaigns;
use RoundlyConsulting\Campaigns\Options\DefaultSendingQueue;
use RoundlyConsulting\Campaigns\Stores\DatabaseCampaignStore;
use RoundlyConsulting\Options\Facades\Options;

/**
 * A Laravel batch pushes every job it is given onto its own queue, overriding the job's — so
 * the per-recipient jobs land wherever the batch was opened. They must land on `sending-queue`
 * (or a stored DefaultSendingQueue option), the queue a host points its workers at.
 */
beforeEach(function (): void {
    useStore(DatabaseCampaignStore::class);
    useDatabaseQueue();
});

it('pushes every recipient job onto the configured sending queue', function (): void {
    config()->set('campaigns.sending-queue', 'sending');

    Campaigns::create('Subject', 'Body')->from('shop@shop.tld')->to(['a@a.tld', 'b@b.tld'])->dispatch();

    expect(DB::table('jobs')->pluck('queue')->all())->toBe(['sending', 'sending']);
});

it('pushes every recipient job onto a stored sending queue option', function (): void {
    Options::set(DefaultSendingQueue::class, 'priority');

    Campaigns::create('Subject', 'Body')->from('shop@shop.tld')->to(['a@a.tld', 'b@b.tld'])->dispatch();

    expect(DB::table('jobs')->pluck('queue')->all())->toBe(['priority', 'priority']);
});

it('delivers once a worker on the sending queue runs the jobs', function (): void {
    config()->set('campaigns.sending-queue', 'sending');

    $campaign = Campaigns::create('Subject', 'Body')->from('shop@shop.tld')->to(['a@a.tld', 'b@b.tld'])->dispatch();

    workQueue('sending');

    expect(DB::table('jobs')->count())->toBe(0)
        ->and(Campaigns::find($campaign->uuid)->progress->sent)->toBe(2);
});
