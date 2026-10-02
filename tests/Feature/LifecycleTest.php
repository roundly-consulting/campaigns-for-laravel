<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Events\CampaignCompleted;
use RoundlyConsulting\Campaigns\Events\CampaignFailed;
use RoundlyConsulting\Campaigns\Exceptions\CampaignAlreadyExists;
use RoundlyConsulting\Campaigns\Exceptions\InvalidCampaignTransition;
use RoundlyConsulting\Campaigns\Exceptions\RecipientNotFound;
use RoundlyConsulting\Campaigns\Facades\Campaigns;

/**
 * Regressions for the lifecycle bugs found by the 2026-09 facade audit. Each one failed
 * against the code before the fix (see the executed plan), on both stores.
 */
beforeEach(fn () => fakeBus());

it('keeps a cancelled campaign cancelled when its batch finishes', function (string $store): void {
    useStore($store);

    $campaign = Campaigns::create('Subject', 'Body')->to('a@a.tld')->dispatch();
    Campaigns::cancel($campaign->uuid);

    Event::fake([CampaignCompleted::class, CampaignFailed::class]);

    // A cancelled batch still runs its `finally` callbacks once the skipped jobs drain.
    finishBatch(Campaigns::campaign($campaign)->batch(), 'catch');
    finishBatch(Campaigns::campaign($campaign)->batch());

    expect(Campaigns::find($campaign->uuid)->progress->status)->toBe(CampaignStatus::Canceled);
    Event::assertNotDispatched(CampaignCompleted::class);
    Event::assertNotDispatched(CampaignFailed::class);
})->with('stores');

it('refuses to start a campaign twice instead of queueing every recipient again', function (string $store): void {
    useStore($store);

    $campaign = Campaigns::create('Subject', 'Body')->to(['a@a.tld', 'b@b.tld'])->dispatch();

    expect(fn () => Campaigns::start($campaign->uuid))->toThrow(InvalidCampaignTransition::class)
        ->and(Campaigns::campaign($campaign)->batch()->added)->toHaveCount(2);
})->with('stores');

it('refuses to start a campaign that was never prepared or already ended', function (string $store): void {
    useStore($store);

    $ended = Campaigns::create('Subject', 'Body')->to('a@a.tld')->prepare();
    Campaigns::cancel($ended);

    expect(fn () => Campaigns::start($ended))->toThrow(InvalidCampaignTransition::class, 'Canceled');
})->with('stores');

it('reports the recipient total while the campaign is sending', function (string $store): void {
    useStore($store);

    $campaign = Campaigns::create('Subject', 'Body')->to(['a@a.tld', 'b@b.tld'])->dispatch();

    expect($campaign->progress->total)->toBe(2)
        ->and(Campaigns::find($campaign->uuid)->progress->total)->toBe(2)
        ->and(Campaigns::campaign($campaign)->progress()->total)->toBe(2)
        ->and(Campaigns::all()->first()->progress->total)->toBe(2);
})->with('stores');

it('never moves a recipient from one campaign to another', function (string $store): void {
    useStore($store);

    $shared = new CampaignRecipient(uuid: '00000000-0000-4000-8000-00000000a001', name: 'John', reachableAt: 'john@doe.tld');

    $a = Campaigns::create('A', 'Body')->to($shared)->prepare();
    $original = Campaigns::campaign($a)->recipient($shared->uuid);

    $b = Campaigns::create('B', 'Body')->to($original)->prepare();
    $copy = Campaigns::campaign($b)->recipients()->first();

    expect(Campaigns::campaign($a)->recipients())->toHaveCount(1)
        ->and(Campaigns::campaign($a)->recipient($shared->uuid)->campaignUuid)->toBe($a->uuid)
        ->and($copy->uuid)->not->toBe($shared->uuid)
        ->and($copy->reachableAt)->toBe('john@doe.tld')
        ->and($copy->campaignUuid)->toBe($b->uuid);
})->with('stores');

it('refuses to mark a recipient of another campaign', function (string $store): void {
    useStore($store);

    $a = Campaigns::create('A', 'Body')->to('a@a.tld')->prepare();
    $b = Campaigns::create('B', 'Body')->to('b@b.tld')->prepare();
    $recipientOfB = Campaigns::campaign($b)->recipients()->first();

    expect(fn () => Campaigns::campaign($a)->markProcessed($recipientOfB))->toThrow(RecipientNotFound::class)
        ->and(Campaigns::campaign($b)->recipient($recipientOfB)->hasBeenProcessed)->toBeFalse();
})->with('stores');

it('keeps an unscoped recipient handed to two campaigns in both', function (string $store): void {
    useStore($store);

    $shared = new CampaignRecipient(uuid: '00000000-0000-4000-8000-00000000a001', name: 'John', reachableAt: 'john@doe.tld');

    $a = Campaigns::prepare(new Campaign(uuid: '00000000-0000-4000-8000-c00000000001', subject: 'A', content: 'Body', fromName: 'Shop', fromAddress: 'shop@shop.tld'), [$shared]);
    $b = Campaigns::prepare(new Campaign(uuid: '00000000-0000-4000-8000-c00000000002', subject: 'B', content: 'Body', fromName: 'Shop', fromAddress: 'shop@shop.tld'), [$shared]);

    expect(Campaigns::campaign($a)->recipients())->toHaveCount(1)
        ->and(Campaigns::campaign($b)->recipients())->toHaveCount(1)
        ->and(Campaigns::campaign($a)->recipient($shared->uuid)->campaignUuid)->toBe($a->uuid);
})->with('stores');

it('refuses to prepare a campaign under a uuid that is already taken', function (string $store): void {
    useStore($store);

    $first = Campaigns::create('First', 'Body')->to(['one@doe.tld', 'two@doe.tld'])->dispatch();

    expect(fn () => Campaigns::create('Other', 'New body')->uuid($first->uuid)->to('three@doe.tld')->dispatch())
        ->toThrow(CampaignAlreadyExists::class, $first->uuid)
        ->and(Campaigns::find($first->uuid))
        ->subject->toBe('First')
        ->progress->status->toBe(CampaignStatus::Processing)
        ->and(Campaigns::campaign($first)->recipients()->pluck('reachableAt')->all())->toBe(['one@doe.tld', 'two@doe.tld'])
        ->and(Campaigns::campaign($first)->batch()->added)->toHaveCount(2);
})->with('stores');
