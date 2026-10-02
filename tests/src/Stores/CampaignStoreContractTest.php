<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignProgress;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Contracts\CampaignStore;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;

/**
 * The persistence contract every CampaignStore must satisfy. The same cases run against
 * both shipped stores, which is what proves they are interchangeable.
 */
function storeCampaign(string $uuid = '00000000-0000-4000-8000-c00000000001'): Campaign
{
    return new Campaign(
        uuid: $uuid,
        subject: 'Subject',
        content: 'Body',
        fromName: 'Shop',
        fromAddress: 'no-reply@shop.tld',
    );
}

function storeRecipient(string $uuid, ?string $campaignUuid = null): CampaignRecipient
{
    return new CampaignRecipient(uuid: $uuid, name: 'Name '.$uuid, reachableAt: $uuid.'@doe.tld', campaignUuid: $campaignUuid);
}

it('round-trips a campaign with its progress, timestamps and batch', function (string $store): void {
    /** @var CampaignStore $store */
    $store = new $store;

    $campaign = storeCampaign();
    $campaign->progress = new CampaignProgress(status: CampaignStatus::Processing, sent: 1, pending: 2, total: 3);
    $campaign->startedAt = Carbon::parse('2026-01-01 10:00:00');
    $campaign->batch = 'batch-1';

    $store->save($campaign);

    expect($store->find($campaign->uuid))
        ->toEqual($campaign)
        ->not->toBe($campaign);
})->with('stores');

it('updates a campaign saved twice', function (string $store): void {
    $store = new $store;

    $campaign = storeCampaign();
    $store->save($campaign);

    $campaign->subject = 'Changed';
    $campaign->endedAt = Carbon::parse('2026-01-02 10:00:00');
    $store->save($campaign);

    expect($store->find($campaign->uuid))
        ->subject->toBe('Changed')
        ->endedAt->toDateTimeString()->toBe('2026-01-02 10:00:00')
        ->and($store->all())->toHaveCount(1);
})->with('stores');

it('hands out copies, never the stored object', function (string $store): void {
    $store = new $store;
    $store->save(storeCampaign());

    $store->find('00000000-0000-4000-8000-c00000000001')->progress->sent = 99;

    expect($store->find('00000000-0000-4000-8000-c00000000001')->progress->sent)->toBe(0);
})->with('stores');

it('returns null for a campaign it does not hold', function (string $store): void {
    expect((new $store)->find('00000000-0000-4000-8000-c0000000ffff'))->toBeNull();
})->with('stores');

it('pages campaigns in creation order', function (string $store): void {
    $store = new $store;
    $store->save(storeCampaign('00000000-0000-4000-8000-c00000000001'));
    $store->save(storeCampaign('00000000-0000-4000-8000-c00000000002'));
    $store->save(storeCampaign('00000000-0000-4000-8000-c00000000003'));

    $uuids = fn (int $offset, int $limit): array => $store->all($offset, $limit)
        ->map(fn (Campaign $campaign): string => $campaign->uuid)
        ->all();

    expect($uuids(0, 10))->toBe([
        '00000000-0000-4000-8000-c00000000001',
        '00000000-0000-4000-8000-c00000000002',
        '00000000-0000-4000-8000-c00000000003',
    ])
        ->and($uuids(1, 1))->toBe(['00000000-0000-4000-8000-c00000000002'])
        ->and($uuids(5, 10))->toBe([]);
})->with('stores');

it('stores recipients under their campaign and lists them in order', function (string $store): void {
    $store = new $store;
    $campaign = '00000000-0000-4000-8000-c00000000001';

    $store->saveRecipients($campaign, [
        storeRecipient('00000000-0000-4000-8000-00000000a001'),
        storeRecipient('00000000-0000-4000-8000-00000000a002'),
        storeRecipient('00000000-0000-4000-8000-00000000a003'),
    ]);

    $uuids = fn (int $offset = 0, ?int $limit = null): array => $store->recipients($campaign, $offset, $limit)
        ->map(fn (CampaignRecipient $recipient): string => $recipient->uuid)
        ->all();

    expect($uuids())->toBe([
        '00000000-0000-4000-8000-00000000a001',
        '00000000-0000-4000-8000-00000000a002',
        '00000000-0000-4000-8000-00000000a003',
    ])
        ->and($uuids(1, 1))->toBe(['00000000-0000-4000-8000-00000000a002'])
        ->and($store->recipients($campaign)->first()->campaignUuid)->toBe($campaign)
        ->and($store->recipients('00000000-0000-4000-8000-c0000000ffff'))->toHaveCount(0);
})->with('stores');

it('updates a recipient saved twice', function (string $store): void {
    $store = new $store;
    $campaign = '00000000-0000-4000-8000-c00000000001';
    $recipient = storeRecipient('00000000-0000-4000-8000-00000000a001', $campaign);

    $store->saveRecipients($campaign, [$recipient]);

    $recipient->errorOccured = true;
    $recipient->errorMessage = 'boom';
    $store->saveRecipients($campaign, [$recipient]);

    expect($store->recipients($campaign))->toHaveCount(1)
        ->and($store->findRecipient($campaign, $recipient->uuid))
        ->errorOccured->toBeTrue()
        ->errorMessage->toBe('boom');
})->with('stores');

it('finds a recipient only within its own campaign', function (string $store): void {
    $store = new $store;

    $store->saveRecipients('00000000-0000-4000-8000-c00000000001', [storeRecipient('00000000-0000-4000-8000-00000000a001')]);
    $store->saveRecipients('00000000-0000-4000-8000-c00000000002', [storeRecipient('00000000-0000-4000-8000-00000000b001')]);

    expect($store->findRecipient('00000000-0000-4000-8000-c00000000001', '00000000-0000-4000-8000-00000000a001'))->not->toBeNull()
        ->and($store->findRecipient('00000000-0000-4000-8000-c00000000001', '00000000-0000-4000-8000-00000000b001'))->toBeNull()
        ->and($store->findRecipient('00000000-0000-4000-8000-c00000000001', 'missing'))->toBeNull();
})->with('stores');

it('keeps one recipient saved under two campaigns in both', function (string $store): void {
    $store = new $store;
    $recipient = storeRecipient('00000000-0000-4000-8000-00000000a001');

    $store->saveRecipients('00000000-0000-4000-8000-c00000000001', [$recipient]);
    $store->saveRecipients('00000000-0000-4000-8000-c00000000002', [$recipient]);

    expect($store->recipients('00000000-0000-4000-8000-c00000000001'))->toHaveCount(1)
        ->and($store->recipients('00000000-0000-4000-8000-c00000000002'))->toHaveCount(1)
        ->and($store->findRecipient('00000000-0000-4000-8000-c00000000001', $recipient->uuid)->campaignUuid)
        ->toBe('00000000-0000-4000-8000-c00000000001');
})->with('stores');
