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
    );

    expect($recipient->toArray())->toBe([
        'uuid' => '0e4a40f8-496f-4032-925f-8693f3dd149a',
        'name' => 'John Doe',
        'reachableAt' => 'john@doe.tld',
        'hasBeenProcessed' => true,
        'errorOccured' => true,
        'errorMessage' => 'boom',
    ]);
});
