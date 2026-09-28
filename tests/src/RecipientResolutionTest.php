<?php

declare(strict_types=1);

use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Facades\Campaigns;
use RoundlyConsulting\Campaigns\Tests\Fixtures\CampaignOwner;
use RoundlyConsulting\Campaigns\Tests\Fixtures\PlainOwner;
use RoundlyConsulting\Contacts\DataTransferObjects\ContactData;
use RoundlyConsulting\Contacts\Enums\ContactType;

/**
 * @return list<CampaignRecipient>
 */
function recipientsFor(string $uuid): array
{
    return Campaigns::campaign($uuid)->recipients()->all();
}

beforeEach(fn () => fakeBus());

it('resolves a HasContacts owner to its primary contact of the send kind', function (): void {
    $owner = CampaignOwner::create(['name' => 'Ada Lovelace']);
    $owner->addEmail('ada@calc.test', primary: true);

    $campaign = Campaigns::create('Subject', 'Body')->to($owner)->prepare();

    $recipients = recipientsFor($campaign->uuid);

    expect($recipients)->toHaveCount(1)
        ->and($recipients[0]->reachableAt)->toBe('ada@calc.test')
        ->and($recipients[0]->name)->toBe('Ada Lovelace');
});

it('includes an unverified owner by default but skips it under onlyVerified', function (): void {
    $owner = CampaignOwner::create(['name' => 'Ada']);
    $owner->addEmail('ada@calc.test', primary: true);

    $included = Campaigns::create('Subject', 'Body')->to($owner)->prepare();
    expect(recipientsFor($included->uuid))->toHaveCount(1);

    $skipped = Campaigns::create('Subject', 'Body')->onlyVerified()->to($owner)->prepare();
    expect(recipientsFor($skipped->uuid))->toHaveCount(0);
});

it('keeps a verified owner under onlyVerified', function (): void {
    $owner = CampaignOwner::create(['name' => 'Ada']);
    $owner->addEmail('ada@calc.test', primary: true)->update(['verified_at' => now()]);

    $campaign = Campaigns::create('Subject', 'Body')->onlyVerified()->to($owner)->prepare();

    expect(recipientsFor($campaign->uuid))->toHaveCount(1);
});

it('resolves the phone contact when viaContactType is Phone', function (): void {
    $owner = CampaignOwner::create(['name' => 'Ada']);
    $owner->addEmail('ada@calc.test', primary: true);
    $phone = $owner->addPhone('+12025550100', primary: true);

    $campaign = Campaigns::create('Subject', 'Body')
        ->viaContactType(ContactType::Phone)
        ->to($owner)
        ->prepare();

    $recipients = recipientsFor($campaign->uuid);

    expect($recipients)->toHaveCount(1)
        ->and($recipients[0]->reachableAt)->toBe($phone->value);
});

it('accepts a viaContactType string', function (): void {
    $owner = CampaignOwner::create(['name' => 'Ada']);
    $phone = $owner->addPhone('+12025550100', primary: true);

    $campaign = Campaigns::create('Subject', 'Body')
        ->viaContactType('phone')
        ->to($owner)
        ->prepare();

    expect(recipientsFor($campaign->uuid)[0]->reachableAt)->toBe($phone->value);
});

it('resolves a Contact model passed directly', function (): void {
    $owner = CampaignOwner::create(['name' => 'Ada']);
    $contact = $owner->addEmail('ada@calc.test');

    $campaign = Campaigns::create('Subject', 'Body')->to($contact)->prepare();

    $recipients = recipientsFor($campaign->uuid);

    expect($recipients)->toHaveCount(1)
        ->and($recipients[0]->reachableAt)->toBe($contact->value)
        ->and($recipients[0]->name)->toBe('Ada');
});

it('skips a directly-passed unverified Contact under onlyVerified', function (): void {
    $owner = CampaignOwner::create(['name' => 'Ada']);
    $contact = $owner->addEmail('ada@calc.test');

    $campaign = Campaigns::create('Subject', 'Body')->onlyVerified()->to($contact)->prepare();

    expect(recipientsFor($campaign->uuid))->toHaveCount(0);
});

it('normalises a mixed iterable of string, owner, and Contact', function (): void {
    $owner = CampaignOwner::create(['name' => 'Ada']);
    $owner->addEmail('ada@calc.test', primary: true);
    $contact = CampaignOwner::create(['name' => 'Bob'])->addEmail('bob@calc.test');

    $campaign = Campaigns::create('Subject', 'Body')
        ->to(['plain@list.test', $owner, $contact])
        ->prepare();

    expect(recipientsFor($campaign->uuid))->toHaveCount(3);
});

it('skips an owner with no contact of the send kind', function (): void {
    $owner = CampaignOwner::create(['name' => 'Ada']);

    $campaign = Campaigns::create('Subject', 'Body')->to($owner)->prepare();

    expect(recipientsFor($campaign->uuid))->toHaveCount(0);
});

it('falls back to the first ordered contact when none is primary', function (): void {
    config()->set('contacts.auto_primary', false);

    $owner = CampaignOwner::create(['name' => 'Ada']);
    $owner->addEmail('ada@calc.test');

    $campaign = Campaigns::create('Subject', 'Body')->to($owner)->prepare();

    expect(recipientsFor($campaign->uuid)[0]->reachableAt)->toBe('ada@calc.test');
});

it('enriches the name from the contact record when the owner has none', function (): void {
    $owner = CampaignOwner::create(['name' => null]);

    $named = $owner->addContact(new ContactData(type: ContactType::Email, value: 'named@x.test', name: 'Named Contact'));
    expect(recipientsFor(Campaigns::create('s', 'b')->to($named)->prepare()->uuid)[0]->name)
        ->toBe('Named Contact');

    $labelled = $owner->addContact(new ContactData(type: ContactType::Email, value: 'label@x.test', label: 'Support'));
    expect(recipientsFor(Campaigns::create('s', 'b')->to($labelled)->prepare()->uuid)[0]->name)
        ->toBe('Support');

    $bare = $owner->addContact(new ContactData(type: ContactType::Email, value: 'bare@x.test'));
    expect(recipientsFor(Campaigns::create('s', 'b')->to($bare)->prepare()->uuid)[0]->name)
        ->toBe('bare@x.test');
});

it('leaves plain string and CampaignRecipient inputs unchanged', function (): void {
    $recipient = new CampaignRecipient(uuid: 'fixed', name: 'John', reachableAt: 'john@x.test');

    $campaign = Campaigns::create('Subject', 'Body')
        ->to('plain@x.test')
        ->to($recipient)
        ->prepare();

    $recipients = recipientsFor($campaign->uuid);

    expect($recipients)->toHaveCount(2)
        ->and(Campaigns::campaign($campaign)->recipient('fixed')->name)->toBe('John');
});

it('rejects a model that does not own contacts', function (): void {
    $plain = PlainOwner::create(['name' => 'nope']);

    expect(fn () => Campaigns::create('Subject', 'Body')->to($plain))
        ->toThrow(InvalidArgumentException::class);
});
