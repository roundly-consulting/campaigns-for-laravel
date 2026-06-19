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

function makeCampaign(string $uuid = 'c-1'): Campaign
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

    expect($manager->find('c-1')->progress->status)->toBe(CampaignStatus::Pending);
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
    $manager->pushRecipientsToCampaign('c-1', [
        new CampaignRecipient(uuid: 'r-1', name: 'John', reachableAt: 'john@doe.tld'),
        new CampaignRecipient(uuid: 'r-2', name: 'Jane', reachableAt: 'jane@doe.tld'),
    ]);
    $manager->start('c-1');

    expect($manager->find('c-1')->progress->status)->toBe(CampaignStatus::Processing)
        ->and($manager->findRecipient('c-1', 'r-1'))->not->toBeNull()
        ->and($manager->findRecipient('c-1', 'missing'))->toBeNull();
})->with('managers');

it('marks recipients processed and failed', function (Closure $make): void {
    fakeBus();
    $manager = $make();

    $manager->prepare(makeCampaign());
    $manager->pushRecipientsToCampaign('c-1', [
        $processed = new CampaignRecipient(uuid: 'r-1', name: 'John', reachableAt: 'john@doe.tld'),
        $failed = new CampaignRecipient(uuid: 'r-2', name: 'Jane', reachableAt: 'jane@doe.tld'),
    ]);

    $campaign = $manager->findOrFail('c-1');
    $manager->markRecipientAsProcessed($campaign, $processed);
    $manager->markRecipientAsFailed($campaign, $failed, 'boom');

    expect($manager->findRecipient('c-1', 'r-1')->hasBeenProcessed)->toBeTrue()
        ->and($manager->findRecipient('c-1', 'r-2'))
        ->errorOccured->toBeTrue()
        ->errorMessage->toBe('boom')
        ->hasBeenProcessed->toBeFalse();
})->with('managers');

it('sets timestamps and cancels a campaign', function (Closure $make): void {
    fakeBus();
    $manager = $make();

    $manager->prepare(makeCampaign());
    $manager->start('c-1');

    expect($manager->find('c-1')->startedAt)->not->toBeNull();

    $manager->cancel('c-1');

    expect($manager->find('c-1'))
        ->progress->status->toBe(CampaignStatus::Canceled)
        ->endedAt->not->toBeNull();
})->with('managers');

it('iterates campaigns with offset and limit', function (Closure $make): void {
    fakeBus();
    $manager = $make();

    $manager->prepare(makeCampaign('c-1'));
    $manager->prepare(makeCampaign('c-2'));
    $manager->prepare(makeCampaign('c-3'));

    $seen = [];
    $manager->onEachCampaign(function (Campaign $campaign) use (&$seen): void {
        $seen[] = $campaign->uuid;
    }, offset: 1, limit: 1);

    expect($seen)->toBe(['c-2']);
})->with('managers');
