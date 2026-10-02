<?php

declare(strict_types=1);

use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Facades\Campaigns;
use RoundlyConsulting\Campaigns\Tests\Fixtures\CampaignOwner;

it('creates, addresses, and dispatches a campaign in one chain', function (): void {
    fakeBus();

    $campaign = Campaigns::create('Subject', '<p>Hi</p>')
        ->from('no-reply@shop.tld', 'Shop')
        ->to(['john@doe.tld', 'jane@doe.tld'])
        ->dispatch();

    expect($campaign)
        ->toBeInstanceOf(Campaign::class)
        ->progress->status->toBe(CampaignStatus::Processing)
        ->fromName->toBe('Shop')
        ->and(Campaigns::campaign($campaign)->recipients())->toHaveCount(2);
});

it('prepare leaves the campaign pending without sending', function (): void {
    fakeBus();

    $campaign = Campaigns::create('Subject', 'Body')
        ->from('no-reply@shop.tld')
        ->to('john@doe.tld')
        ->prepare();

    expect($campaign->progress->status)->toBe(CampaignStatus::Pending)
        ->and($campaign->fromName)->toBe('no-reply@shop.tld');
});

it('normalises a single CampaignRecipient and an iterable', function (): void {
    fakeBus();

    $recipient = new CampaignRecipient(uuid: 'fixed-uuid', name: 'John', reachableAt: 'john@doe.tld');

    $campaign = Campaigns::create('Subject', 'Body')
        ->to($recipient)
        ->to(new ArrayIterator(['a@a.tld', 'b@b.tld']))
        ->prepare();

    expect(Campaigns::campaign($campaign)->recipients())->toHaveCount(3)
        ->and(Campaigns::campaign($campaign)->recipient('fixed-uuid')->name)->toBe('John')
        ->and($recipient->campaignUuid)->toBeNull();
});

it('respects a supplied uuid and subject/content overrides', function (): void {
    fakeBus();

    $campaign = Campaigns::create('Old', 'Old')
        ->uuid('11111111-1111-1111-1111-111111111111')
        ->subject('New Subject')
        ->content('New Body')
        ->prepare();

    expect($campaign->uuid)->toBe('11111111-1111-1111-1111-111111111111')
        ->and($campaign->subject)->toBe('New Subject')
        ->and($campaign->content)->toBe('New Body')
        ->and(Campaigns::find('11111111-1111-1111-1111-111111111111'))->not->toBeNull();
});

it('rejects invalid recipient input', function (): void {
    fakeBus();

    expect(fn () => Campaigns::create('Subject', 'Body')->to(new ArrayIterator([123])))
        ->toThrow(InvalidArgumentException::class);
});

it('exposes find and all through the facade', function (): void {
    fakeBus();

    Campaigns::create('One', 'Body')->uuid('uuid-1')->prepare();
    Campaigns::create('Two', 'Body')->uuid('uuid-2')->prepare();

    expect(Campaigns::all()->map(fn (Campaign $campaign): string => $campaign->uuid)->all())
        ->toBe(['uuid-1', 'uuid-2']);
});

it('cancels through the facade', function (): void {
    fakeBus();

    $campaign = Campaigns::create('One', 'Body')->to('a@a.tld')->dispatch();

    Campaigns::cancel($campaign->uuid);

    expect(Campaigns::find($campaign->uuid)->progress->status)->toBe(CampaignStatus::Canceled);
});

it('adds each address once, however it is passed to to()', function (): void {
    fakeBus();

    $owner = CampaignOwner::create(['name' => 'Jane']);
    $owner->addEmail('jane@doe.tld', primary: true);

    $campaign = Campaigns::create('Subject', 'Body')
        ->to($owner)
        ->to(['jane@doe.tld', 'Bob@Doe.tld', 'bob@doe.tld'])
        ->to(new CampaignRecipient(uuid: 'bob-again', name: 'Bob', reachableAt: 'bob@doe.tld'))
        ->to(collect([$owner]))
        ->dispatch();

    expect(Campaigns::campaign($campaign)->recipients()->pluck('name', 'reachableAt')->all())
        ->toBe(['jane@doe.tld' => 'Jane', 'Bob@Doe.tld' => 'Bob@Doe.tld'])
        ->and(Campaigns::campaign($campaign)->batch()->added)->toHaveCount(2);
});
