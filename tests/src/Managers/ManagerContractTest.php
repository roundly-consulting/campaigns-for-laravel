<?php

declare(strict_types=1);

use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Exceptions\CampaignNotFound;
use RoundlyConsulting\Campaigns\Managers\DatabaseManager;
use RoundlyConsulting\Campaigns\Managers\InMemoryManager;
use RoundlyConsulting\Campaigns\Managers\Manager;

/**
 * Shared lifecycle assertions every Manager implementation must satisfy. The
 * same suite runs against both the in-memory and database managers so they are
 * proven equivalent.
 */
dataset('managers', [
    'in-memory' => fn (): Manager => new InMemoryManager,
    'database' => fn (): Manager => new DatabaseManager,
]);

function makeCampaign(string $uuid = '00000000-0000-4000-8000-c00000000001'): Campaign
{
    return new Campaign(
        uuid: $uuid,
        subject: 'Subject',
        content: 'Body',
        fromName: 'Shop',
        fromAddress: 'no-reply@shop.tld',
    );
}

it('prepares a campaign to pending', function (Closure $make): void {
    fakeBus();
    $manager = $make();

    $manager->prepare(makeCampaign());

    expect($manager->find('00000000-0000-4000-8000-c00000000001')->progress->status)->toBe(CampaignStatus::Pending);
})->with('managers');

it('returns null when finding a missing campaign', function (Closure $make): void {
    expect($make()->find('missing'))->toBeNull();
})->with('managers');

it('throws when finding a missing campaign with findOrFail', function (Closure $make): void {
    expect(fn () => $make()->findOrFail('missing'))->toThrow(CampaignNotFound::class);
})->with('managers');

it('throws when starting or cancelling a missing campaign', function (Closure $make): void {
    $manager = $make();

    expect(fn () => $manager->start('missing'))->toThrow(CampaignNotFound::class);
    expect(fn () => $manager->cancel('missing'))->toThrow(CampaignNotFound::class);
})->with('managers');

it('pushes recipients and starts processing', function (Closure $make): void {
    fakeBus();
    $manager = $make();

    $manager->prepare(makeCampaign());
    $manager->pushRecipientsToCampaign('00000000-0000-4000-8000-c00000000001', [
        new CampaignRecipient(uuid: '00000000-0000-4000-8000-00000000a001', name: 'John', reachableAt: 'john@doe.tld'),
        new CampaignRecipient(uuid: '00000000-0000-4000-8000-0000000000a2', name: 'Jane', reachableAt: 'jane@doe.tld'),
    ]);
    $manager->start('00000000-0000-4000-8000-c00000000001');

    expect($manager->find('00000000-0000-4000-8000-c00000000001')->progress->status)->toBe(CampaignStatus::Processing)
        ->and($manager->findRecipient('00000000-0000-4000-8000-c00000000001', '00000000-0000-4000-8000-00000000a001'))->not->toBeNull()
        ->and($manager->findRecipient('00000000-0000-4000-8000-c00000000001', 'missing'))->toBeNull();
})->with('managers');

it('marks recipients processed and failed', function (Closure $make): void {
    fakeBus();
    $manager = $make();

    $manager->prepare(makeCampaign());
    $manager->pushRecipientsToCampaign('00000000-0000-4000-8000-c00000000001', [
        $processed = new CampaignRecipient(uuid: '00000000-0000-4000-8000-00000000a001', name: 'John', reachableAt: 'john@doe.tld'),
        $failed = new CampaignRecipient(uuid: '00000000-0000-4000-8000-0000000000a2', name: 'Jane', reachableAt: 'jane@doe.tld'),
    ]);

    $campaign = $manager->findOrFail('00000000-0000-4000-8000-c00000000001');
    $manager->markRecipientAsProcessed($campaign, $processed);
    $manager->markRecipientAsFailed($campaign, $failed, 'boom');

    expect($manager->findRecipient('00000000-0000-4000-8000-c00000000001', '00000000-0000-4000-8000-00000000a001')->hasBeenProcessed)->toBeTrue()
        ->and($manager->findRecipient('00000000-0000-4000-8000-c00000000001', '00000000-0000-4000-8000-0000000000a2'))
        ->errorOccured->toBeTrue()
        ->errorMessage->toBe('boom')
        ->hasBeenProcessed->toBeFalse();
})->with('managers');

it('sets timestamps and cancels a campaign', function (Closure $make): void {
    fakeBus();
    $manager = $make();

    $manager->prepare(makeCampaign());
    $manager->start('00000000-0000-4000-8000-c00000000001');

    expect($manager->find('00000000-0000-4000-8000-c00000000001')->startedAt)->not->toBeNull();

    $manager->cancel('00000000-0000-4000-8000-c00000000001');

    expect($manager->find('00000000-0000-4000-8000-c00000000001'))
        ->progress->status->toBe(CampaignStatus::Canceled)
        ->endedAt->not->toBeNull();
})->with('managers');

it('iterates campaigns with offset and limit', function (Closure $make): void {
    fakeBus();
    $manager = $make();

    $manager->prepare(makeCampaign('00000000-0000-4000-8000-c00000000001'));
    $manager->prepare(makeCampaign('00000000-0000-4000-8000-c00000000002'));
    $manager->prepare(makeCampaign('00000000-0000-4000-8000-c00000000003'));

    $seen = [];
    $manager->onEachCampaign(function (Campaign $campaign) use (&$seen): void {
        $seen[] = $campaign->uuid;
    }, offset: 1, limit: 1);

    expect($seen)->toBe(['00000000-0000-4000-8000-c00000000002']);
})->with('managers');
