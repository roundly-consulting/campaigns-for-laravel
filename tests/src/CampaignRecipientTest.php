<?php

declare(strict_types=1);

use RoundlyConsulting\Campaigns\CampaignRecipient;

it('defaults a recipient to unprocessed without errors', function (): void {
    $recipient = new CampaignRecipient(
        uuid: '0e4a40f8-496f-4032-925f-8693f3dd149a',
        name: 'John Doe',
        reachableAt: 'john@doe.tld',
    );

    expect($recipient)
        ->hasBeenProcessed->toBeFalse()
        ->errorOccured->toBeFalse()
        ->errorMessage->toBeNull();
});

it('serialises to an array', function (): void {
    $recipient = new CampaignRecipient(
        uuid: '0e4a40f8-496f-4032-925f-8693f3dd149a',
        name: 'John Doe',
        reachableAt: 'john@doe.tld',
        hasBeenProcessed: true,
        errorOccured: true,
        errorMessage: 'boom',
        campaignUuid: '00000000-0000-4000-8000-c00000000001',
    );

    expect($recipient->toArray())->toBe([
        'uuid' => '0e4a40f8-496f-4032-925f-8693f3dd149a',
        'name' => 'John Doe',
        'reachableAt' => 'john@doe.tld',
        'hasBeenProcessed' => true,
        'errorOccured' => true,
        'errorMessage' => 'boom',
        'campaignUuid' => '00000000-0000-4000-8000-c00000000001',
    ]);
});

it('belongs only to the campaign it was added to', function (): void {
    $unscoped = new CampaignRecipient(uuid: 'r-1', name: 'John', reachableAt: 'john@doe.tld');
    $scoped = $unscoped->forCampaign('campaign-a');

    expect($unscoped->belongsTo('campaign-a'))->toBeFalse()
        ->and($unscoped->campaignUuid)->toBeNull()
        ->and($scoped->belongsTo('campaign-a'))->toBeTrue()
        ->and($scoped->belongsTo('campaign-b'))->toBeFalse()
        ->and($scoped->uuid)->toBe('r-1');
});

it('keeps its identity when re-scoped to its own campaign', function (): void {
    $recipient = new CampaignRecipient(uuid: 'r-1', name: 'John', reachableAt: 'john@doe.tld', hasBeenProcessed: true, campaignUuid: 'campaign-a');

    expect($recipient->forCampaign('campaign-a'))
        ->uuid->toBe('r-1')
        ->hasBeenProcessed->toBeTrue()
        ->not->toBe($recipient);
});

it('becomes a fresh recipient when added to another campaign', function (): void {
    $recipient = new CampaignRecipient(
        uuid: 'r-1',
        name: 'John',
        reachableAt: 'john@doe.tld',
        hasBeenProcessed: true,
        errorOccured: true,
        errorMessage: 'boom',
        campaignUuid: 'campaign-a',
    );

    $copy = $recipient->forCampaign('campaign-b');

    expect($copy)
        ->uuid->not->toBe('r-1')
        ->name->toBe('John')
        ->reachableAt->toBe('john@doe.tld')
        ->hasBeenProcessed->toBeFalse()
        ->errorOccured->toBeFalse()
        ->errorMessage->toBeNull()
        ->campaignUuid->toBe('campaign-b')
        ->and($recipient->campaignUuid)->toBe('campaign-a');
});

it('scopes a list and rejects anything that is not a recipient', function (): void {
    $scoped = CampaignRecipient::scopeAll(
        [new CampaignRecipient(uuid: 'r-1', name: 'John', reachableAt: 'john@doe.tld')],
        'campaign-a',
    );

    expect($scoped)->toHaveCount(1)
        ->and($scoped[0]->campaignUuid)->toBe('campaign-a')
        ->and(fn () => CampaignRecipient::scopeAll(['john@doe.tld'], 'campaign-a'))
        ->toThrow(InvalidArgumentException::class, 'CampaignRecipient');
});
