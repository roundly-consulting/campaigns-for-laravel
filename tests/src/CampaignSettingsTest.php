<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\Campaigns\Facades\Campaigns;
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
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

beforeEach(fn () => fakeBus());

function settings(): CampaignSettings
{
    return Campaigns::settings();
}

it('each option casts and defaults from config', function (): void {
    expect((new DefaultFromName)->castAs())->toBe('string')
        ->and((new DefaultFromAddress)->castAs())->toBe('string')
        ->and((new DefaultChannel)->castAs())->toBe('string')
        ->and((new DefaultSendingQueue)->castAs())->toBe('string')
        ->and((new OnlyVerifiedRecipients)->castAs())->toBe('boolean')
        ->and((new DefaultRecipientContactType)->castAs())->toContain(ContactType::class);

    config()->set('campaigns.from-address', 'seed@acme.test');
    config()->set('campaigns.sending-queue', 'seed-sending');
    config()->set('campaigns.recipients.only-verified', true);
    config()->set('campaigns.recipients.contact-type', 'phone');

    expect((new DefaultFromAddress)->default())->toBe('seed@acme.test')
        ->and((new DefaultSendingQueue)->default())->toBe('seed-sending')
        ->and((new OnlyVerifiedRecipients)->default())->toBeTrue()
        ->and((new DefaultRecipientContactType)->default())->toBe(ContactType::Phone);
});

it('refuses an unknown recipient contact type instead of reading it as email (strict config)', function (mixed $value): void {
    config()->set('campaigns.recipients.contact-type', $value);

    expect(fn () => (new DefaultRecipientContactType)->default())->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [campaigns.recipients.contact-type] must be one of [email, phone, address, url, social, custom]',
    );
})->with(['typo' => ['emial'], 'capitalised' => ['Email'], 'blank' => ['']]);

it('uses email when the recipient contact type is absent (strict config)', function (): void {
    config()->set('campaigns.recipients.contact-type', null);

    expect((new DefaultRecipientContactType)->default())->toBe(ContactType::Email);
});

it('refuses a blank or non-string channel or queue, and a non-string sender (strict config)', function (string $key, mixed $value, Closure $read): void {
    config()->set($key, $value);

    expect($read)->toThrow(InvalidConfigurationException::class, "Configuration value [{$key}]");
})->with([
    'channel blank' => ['campaigns.notification-channel', '', fn () => (new DefaultChannel)->default()],
    'channel array' => ['campaigns.notification-channel', ['mail'], fn () => (new DefaultChannel)->default()],
    'queue blank' => ['campaigns.sending-queue', ' ', fn () => (new DefaultSendingQueue)->default()],
    'queue int' => ['campaigns.sending-queue', 5, fn () => (new DefaultSendingQueue)->default()],
    'from name array' => ['campaigns.from-name', ['Acme'], fn () => (new DefaultFromName)->default()],
    'from address int' => ['campaigns.from-address', 1, fn () => (new DefaultFromAddress)->default()],
]);

it('keeps a blank sender as the documented "use the mailer from" value (strict config)', function (): void {
    config()->set('campaigns.from-name', '');
    config()->set('campaigns.from-address', null);

    expect((new DefaultFromName)->default())->toBe('')
        ->and((new DefaultFromAddress)->default())->toBe('');
});

it('reads config defaults when no option is set', function (): void {
    expect(settings()->fromAddress())->toBe('')
        ->and(settings()->fromName())->toBe('')
        ->and(settings()->notificationChannel())->toBe('mail')
        ->and(settings()->sendingQueue())->toBe('default')
        ->and(settings()->onlyVerifiedRecipients())->toBeFalse()
        ->and(settings()->defaultRecipientContactType())->toBe(ContactType::Email);
});

it('returns the stored option value once set', function (): void {
    Options::set(DefaultFromAddress::class, 'news@acme.test');
    Options::set(DefaultChannel::class, 'vonage');
    Options::set(DefaultSendingQueue::class, 'sends');
    Options::set(OnlyVerifiedRecipients::class, true);
    Options::set(DefaultRecipientContactType::class, ContactType::Phone);

    expect(settings()->fromAddress())->toBe('news@acme.test')
        ->and(settings()->notificationChannel())->toBe('vonage')
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

    expect(Campaigns::campaign($campaign)->recipients())->toHaveCount(0);
});

it('lets an explicit onlyVerified(false) override the option', function (): void {
    Options::set(OnlyVerifiedRecipients::class, true);

    $owner = CampaignOwner::create(['name' => 'Ada']);
    $owner->addEmail('ada@calc.test', primary: true);

    $campaign = Campaigns::create('Subject', 'Body')->onlyVerified(false)->to($owner)->prepare();

    expect(Campaigns::campaign($campaign)->recipients())->toHaveCount(1);
});

it('drives kind resolution from DefaultRecipientContactType', function (): void {
    Options::set(DefaultRecipientContactType::class, ContactType::Phone);

    $owner = CampaignOwner::create(['name' => 'Ada']);
    $owner->addEmail('ada@calc.test', primary: true);
    $phone = $owner->addPhone('+12025550100', primary: true);

    $campaign = Campaigns::create('Subject', 'Body')->to($owner)->prepare();

    $recipients = Campaigns::campaign($campaign)->recipients()->all();

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

    $recipients = Campaigns::campaign($campaign)->recipients()->all();

    expect($recipients[0]->reachableAt)->toBe('ada@calc.test');
});

it('reads the only-verified switch words strictly', function (string $value, bool $expected): void {
    config()->set('campaigns.recipients.only-verified', $value);

    expect((new OnlyVerifiedRecipients)->default())->toBe($expected)
        ->and(settings()->onlyVerifiedRecipients())->toBe($expected);
})->with([
    'off' => ['off', false],
    'no' => ['no', false],
    '0' => ['0', false],
    'on' => ['on', true],
    'yes' => ['yes', true],
    '1' => ['1', true],
]);

it('throws on an only-verified typo instead of reading it as off (strict config)', function (Closure $read): void {
    config()->set('campaigns.recipients.only-verified', 'disabled');

    expect($read)->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [campaigns.recipients.only-verified] must be a boolean (true/false, 1/0, on/off or yes/no), [disabled] given.',
    );
})->with([
    'option default' => [fn (): bool => (new OnlyVerifiedRecipients)->default()],
    'settings' => [fn (): bool => settings()->onlyVerifiedRecipients()],
    'about' => [fn (): int => Artisan::call('about', ['--only' => 'campaigns'])],
]);
