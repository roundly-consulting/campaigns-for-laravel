<?php

declare(strict_types=1);

use RoundlyConsulting\Campaigns\Facades\Campaigns;
use RoundlyConsulting\Campaigns\Managers\InMemoryManager;
use RoundlyConsulting\Campaigns\Options\DefaultBatchQueue;
use RoundlyConsulting\Campaigns\Options\DefaultChannel;
use RoundlyConsulting\Campaigns\Options\DefaultFromAddress;
use RoundlyConsulting\Campaigns\Options\DefaultFromName;
use RoundlyConsulting\Campaigns\Options\DefaultRecipientContactType;
use RoundlyConsulting\Campaigns\Options\DefaultSendingQueue;
use RoundlyConsulting\Campaigns\Options\OnlyVerifiedRecipients;
use RoundlyConsulting\Campaigns\Support\CampaignSettings;
use RoundlyConsulting\Campaigns\Tests\Fixtures\CampaignOwner;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Options\Facades\Options;

beforeEach(fn () => fakeBus());

function settings(): CampaignSettings
{
    return app(CampaignSettings::class);
}

it('each option casts and defaults from config', function (): void {
    expect((new DefaultFromName)->castAs())->toBe('string')
        ->and((new DefaultFromAddress)->castAs())->toBe('string')
        ->and((new DefaultChannel)->castAs())->toBe('string')
        ->and((new DefaultBatchQueue)->castAs())->toBe('string')
        ->and((new DefaultSendingQueue)->castAs())->toBe('string')
        ->and((new OnlyVerifiedRecipients)->castAs())->toBe('boolean')
        ->and((new DefaultRecipientContactType)->castAs())->toContain(ContactType::class);

    config()->set('campaigns.from-address', 'seed@acme.test');
    config()->set('campaigns.batch-queue', 'seed-batch');
    config()->set('campaigns.recipients.only-verified', true);
    config()->set('campaigns.recipients.contact-type', 'phone');

    expect((new DefaultFromAddress)->default())->toBe('seed@acme.test')
        ->and((new DefaultBatchQueue)->default())->toBe('seed-batch')
        ->and((new OnlyVerifiedRecipients)->default())->toBeTrue()
        ->and((new DefaultRecipientContactType)->default())->toBe(ContactType::Phone);
});

it('falls back to an unknown recipient contact type as email', function (): void {
    config()->set('campaigns.recipients.contact-type', 'nonsense');

    expect((new DefaultRecipientContactType)->default())->toBe(ContactType::Email);
});

it('reads config defaults when no option is set', function (): void {
    expect(settings()->fromAddress())->toBe('')
        ->and(settings()->fromName())->toBe('')
        ->and(settings()->notificationChannel())->toBe('mail')
        ->and(settings()->batchQueue())->toBe('default')
        ->and(settings()->sendingQueue())->toBe('default')
        ->and(settings()->onlyVerifiedRecipients())->toBeFalse()
        ->and(settings()->defaultRecipientContactType())->toBe(ContactType::Email);
});

it('returns the stored option value once set', function (): void {
    Options::set(DefaultFromAddress::class, 'news@acme.test');
    Options::set(DefaultChannel::class, 'vonage');
    Options::set(DefaultBatchQueue::class, 'blasts');
    Options::set(DefaultSendingQueue::class, 'sends');
    Options::set(OnlyVerifiedRecipients::class, true);
    Options::set(DefaultRecipientContactType::class, ContactType::Phone);

    expect(settings()->fromAddress())->toBe('news@acme.test')
        ->and(settings()->notificationChannel())->toBe('vonage')
        ->and(settings()->batchQueue())->toBe('blasts')
        ->and(settings()->sendingQueue())->toBe('sends')
        ->and(settings()->onlyVerifiedRecipients())->toBeTrue()
        ->and(settings()->defaultRecipientContactType())->toBe(ContactType::Phone);
});

it('sends a from-less campaign from the stored default sender', function (): void {
    Options::set(DefaultFromAddress::class, 'news@acme.test');
    Options::set(DefaultFromName::class, 'Acme');

    $campaign = Campaigns::create('Subject', 'Body')->to('x@y.test')->prepare();

    expect($campaign->fromAddress)->toBe('news@acme.test')
        ->and($campaign->fromName)->toBe('Acme');
});

it('keeps the original empty sender default when nothing is configured', function (): void {
    $campaign = Campaigns::create('Subject', 'Body')->to('x@y.test')->prepare();

    expect($campaign->fromAddress)->toBe('')
        ->and($campaign->fromName)->toBe('');
});

it('lets an explicit from override the option', function (): void {
    Options::set(DefaultFromAddress::class, 'option@acme.test');

    $campaign = Campaigns::create('Subject', 'Body')
        ->from('explicit@acme.test', 'Explicit')
        ->to('x@y.test')
        ->prepare();

    expect($campaign->fromAddress)->toBe('explicit@acme.test')
        ->and($campaign->fromName)->toBe('Explicit');
});

it('skips unverified owners when OnlyVerifiedRecipients is on without an explicit toggle', function (): void {
    Options::set(OnlyVerifiedRecipients::class, true);

    $owner = CampaignOwner::create(['name' => 'Ada']);
    $owner->addEmail('ada@calc.test', primary: true);

    $campaign = Campaigns::create('Subject', 'Body')->to($owner)->prepare();

    expect(InMemoryManager::$recipients[$campaign->uuid] ?? [])->toHaveCount(0);
});

it('lets an explicit onlyVerified(false) override the option', function (): void {
    Options::set(OnlyVerifiedRecipients::class, true);

    $owner = CampaignOwner::create(['name' => 'Ada']);
    $owner->addEmail('ada@calc.test', primary: true);

    $campaign = Campaigns::create('Subject', 'Body')->onlyVerified(false)->to($owner)->prepare();

    expect(InMemoryManager::$recipients[$campaign->uuid] ?? [])->toHaveCount(1);
});

it('drives kind resolution from DefaultRecipientContactType', function (): void {
    Options::set(DefaultRecipientContactType::class, ContactType::Phone);

    $owner = CampaignOwner::create(['name' => 'Ada']);
    $owner->addEmail('ada@calc.test', primary: true);
    $phone = $owner->addPhone('+12025550100', primary: true);

    $campaign = Campaigns::create('Subject', 'Body')->to($owner)->prepare();

    $recipients = array_values(InMemoryManager::$recipients[$campaign->uuid]);

    expect($recipients[0]->reachableAt)->toBe($phone->value);
});

it('lets an explicit viaContactType override the option', function (): void {
    Options::set(DefaultRecipientContactType::class, ContactType::Phone);

    $owner = CampaignOwner::create(['name' => 'Ada']);
    $owner->addEmail('ada@calc.test', primary: true);
    $owner->addPhone('+12025550100', primary: true);

    $campaign = Campaigns::create('Subject', 'Body')
        ->viaContactType(ContactType::Email)
        ->to($owner)
        ->prepare();

    $recipients = array_values(InMemoryManager::$recipients[$campaign->uuid]);

    expect($recipients[0]->reachableAt)->toBe('ada@calc.test');
});
