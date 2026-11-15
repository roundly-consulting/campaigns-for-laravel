<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Contracts\CampaignStore;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Events\RecipientFailed;
use RoundlyConsulting\Campaigns\Events\RecipientProcessed;
use RoundlyConsulting\Campaigns\Facades\Campaigns;
use RoundlyConsulting\Campaigns\Jobs\SendCampaignEmail;
use RoundlyConsulting\Campaigns\Jobs\SendCampaignNotification;
use RoundlyConsulting\Campaigns\Models\CampaignRecipientRecord;
use RoundlyConsulting\Campaigns\Stores\DatabaseCampaignStore;
use RoundlyConsulting\Campaigns\Tests\Fixtures\ModelRecipientJob;
use RoundlyConsulting\Campaigns\Tests\Fixtures\PlainOwner;

beforeEach(function (): void {
    ModelRecipientJob::$delivered = [];
});

afterEach(function (): void {
    Relation::morphMap([], merge: false);
});

describe('the value object', function (): void {
    it('stands for a model by its morph type and key', function (): void {
        $user = PlainOwner::create(['name' => 'Jane']);

        $recipient = CampaignRecipient::forModel($user, 'Jane', meta: ['locale' => 'sk']);

        expect($recipient)
            ->name->toBe('Jane')
            ->reachableAt->toBeNull()
            ->recipientType->toBe(PlainOwner::class)
            ->recipientId->toBe($user->id)
            ->meta->toBe(['locale' => 'sk'])
            ->and(Str::isUuid($recipient->uuid))->toBeTrue()
            ->and($recipient->hasModel())->toBeTrue()
            ->and($recipient->isFor($user))->toBeTrue()
            ->and($recipient->isFor(PlainOwner::create(['name' => 'Other'])))->toBeFalse()
            ->and($recipient->model()?->is($user))->toBeTrue();
    });

    it('keeps an optional address next to the model', function (): void {
        $recipient = CampaignRecipient::forModel(PlainOwner::create(), 'Jane', 'jane@doe.tld');

        expect($recipient->reachableAt)->toBe('jane@doe.tld')
            ->and($recipient->toArray())->toMatchArray([
                'reachableAt' => 'jane@doe.tld',
                'recipientType' => PlainOwner::class,
                'meta' => null,
            ]);
    });

    it('uses the morph map alias and resolves through it', function (): void {
        Relation::morphMap(['user' => PlainOwner::class]);
        $user = PlainOwner::create(['name' => 'Jane']);

        $recipient = CampaignRecipient::forModel($user, 'Jane');

        expect($recipient->recipientType)->toBe('user')
            ->and($recipient->model()?->is($user))->toBeTrue();
    });

    it('requires reachableAt when it stands for no model', function (): void {
        expect(fn () => new CampaignRecipient(uuid: 'r-1', name: 'John'))
            ->toThrow(InvalidArgumentException::class, 'reachableAt')
            ->and(fn () => new CampaignRecipient(uuid: 'r-1', name: 'John', reachableAt: null))
            ->toThrow(InvalidArgumentException::class, 'reachableAt');
    });

    it('refuses half a model', function (): void {
        expect(fn () => new CampaignRecipient(uuid: 'r-1', name: 'John', reachableAt: 'j@d.tld', recipientType: 'user'))
            ->toThrow(InvalidArgumentException::class, 'both')
            ->and(fn () => new CampaignRecipient(uuid: 'r-1', name: 'John', reachableAt: 'j@d.tld', recipientId: 5))
            ->toThrow(InvalidArgumentException::class, 'both');
    });

    it('refuses an unsaved model', function (): void {
        expect(fn () => CampaignRecipient::forModel(new PlainOwner, 'Jane'))
            ->toThrow(InvalidArgumentException::class, 'saved model');
    });

    it('resolves no model for an address recipient or a deleted model', function (): void {
        $user = PlainOwner::create();
        $recipient = CampaignRecipient::forModel($user, 'Jane');
        $user->delete();

        expect($recipient->model())->toBeNull()
            ->and((new CampaignRecipient(uuid: 'r-1', name: 'John', reachableAt: 'j@d.tld'))->model())->toBeNull()
            ->and((new CampaignRecipient(uuid: 'r-1', name: 'John', recipientType: 'NotAModel', recipientId: 1))->model())->toBeNull();
    });

    it('carries the model into a copy for another campaign', function (): void {
        $user = PlainOwner::create();
        $recipient = CampaignRecipient::forModel($user, 'Jane', meta: ['k' => 'v'])->forCampaign('campaign-a');

        expect($recipient->forCampaign('campaign-b'))
            ->uuid->not->toBe($recipient->uuid)
            ->recipientType->toBe(PlainOwner::class)
            ->recipientId->toBe($user->id)
            ->meta->toBe(['k' => 'v']);
    });

    it('unserialises a recipient queued before the model fields existed', function (): void {
        // A job queued on 2.0 carries no recipientType / recipientId / meta at all.
        $class = CampaignRecipient::class;
        $serialized = 'O:'.strlen($class).':"'.$class.'":7:{s:4:"uuid";s:3:"r-1";s:4:"name";s:4:"John";s:11:"reachableAt";s:7:"j@d.tld";s:16:"hasBeenProcessed";b:0;s:12:"errorOccured";b:0;s:12:"errorMessage";N;s:12:"campaignUuid";N;}';

        $recipient = unserialize($serialized);

        expect($recipient)
            ->reachableAt->toBe('j@d.tld')
            ->recipientType->toBeNull()
            ->recipientId->toBeNull()
            ->meta->toBeNull()
            ->and($recipient->hasModel())->toBeFalse();
    });
});

describe('the database store', function (): void {
    beforeEach(fn () => useStore(DatabaseCampaignStore::class));

    it('round-trips a model recipient with its meta', function (): void {
        $user = PlainOwner::create(['name' => 'Jane']);
        $store = app(CampaignStore::class);
        $recipient = CampaignRecipient::forModel($user, 'Jane', meta: ['locale' => 'sk', 'tags' => ['a']])->forCampaign('00000000-0000-4000-8000-c00000000001');

        $store->saveRecipients('00000000-0000-4000-8000-c00000000001', [$recipient]);

        $read = $store->findRecipient('00000000-0000-4000-8000-c00000000001', $recipient->uuid);

        expect($read)->toEqual($recipient)
            ->and($read->recipientId)->toBe($user->id)
            ->and(CampaignRecipientRecord::query()->sole())
            ->reachable_at->toBeNull()
            ->recipient_type->toBe(PlainOwner::class)
            ->and(CampaignRecipientRecord::query()->sole()->recipient?->is($user))->toBeTrue();
    });

    it('reads a bigint key back as an int, however it was given', function (): void {
        $store = app(CampaignStore::class);
        $recipient = new CampaignRecipient(uuid: (string) Str::uuid(), name: 'Jane', recipientType: 'user', recipientId: '42');

        $store->saveRecipients('00000000-0000-4000-8000-c00000000001', [$recipient]);

        expect($store->findRecipient('00000000-0000-4000-8000-c00000000001', $recipient->uuid)?->recipientId)->toBe(42);
    });

    it('keeps an address recipient exactly as before', function (): void {
        $store = app(CampaignStore::class);
        $recipient = new CampaignRecipient(uuid: (string) Str::uuid(), name: 'John', reachableAt: 'john@doe.tld', campaignUuid: '00000000-0000-4000-8000-c00000000001');

        $store->saveRecipients('00000000-0000-4000-8000-c00000000001', [$recipient]);

        expect($store->findRecipient('00000000-0000-4000-8000-c00000000001', $recipient->uuid))->toEqual($recipient)
            ->and(CampaignRecipientRecord::query()->sole())
            ->reachable_at->toBe('john@doe.tld')
            ->recipient_type->toBeNull()
            ->recipient_id->toBeNull()
            ->meta->toBeNull()
            ->recipient->toBeNull();
    });

    it('builds a model recipient row from the factory', function (): void {
        $user = PlainOwner::create();

        $record = CampaignRecipientRecord::factory()->forModel($user)->create();

        expect($record->reachable_at)->toBeNull()
            ->and($record->recipient?->is($user))->toBeTrue()
            ->and(CampaignRecipientRecord::factory()->forModel($user, withAddress: true)->create()->reachable_at)->toBeString();
    });
});

describe('delivery through a worker', function (): void {
    beforeEach(function (): void {
        useStore(DatabaseCampaignStore::class);
        useDatabaseQueue();
    });

    it('hands a model recipient without an address to a host job, which resolves the model', function (): void {
        config()->set('campaigns.process-recipient-job', ModelRecipientJob::class);
        Event::fake([RecipientProcessed::class]);

        $jane = PlainOwner::create(['name' => 'Jane']);
        $john = PlainOwner::create(['name' => 'John']);

        $campaign = Campaigns::create('Announcement', 'Hello')
            ->to([
                CampaignRecipient::forModel($jane, 'Jane', meta: ['channel' => 'in-app']),
                CampaignRecipient::forModel($john, 'John'),
                CampaignRecipient::forModel($jane, 'Jane again'),
                'ops@shop.tld',
            ])
            ->dispatch();

        workQueue();

        expect(ModelRecipientJob::$delivered)->toHaveCount(3)
            ->and(ModelRecipientJob::$delivered[0]['model']?->is($jane))->toBeTrue()
            ->and(ModelRecipientJob::$delivered[0]['meta'])->toBe(['channel' => 'in-app'])
            ->and(ModelRecipientJob::$delivered[0]['reachableAt'])->toBeNull()
            ->and(ModelRecipientJob::$delivered[1]['model']?->is($john))->toBeTrue()
            ->and(ModelRecipientJob::$delivered[2]['model'])->toBeNull()
            ->and(Campaigns::find($campaign->uuid)->progress->sent)->toBe(2);

        Event::assertDispatched(RecipientProcessed::class, fn (RecipientProcessed $event): bool => $event->recipient->isFor($jane)
            && $event->recipient->meta === ['channel' => 'in-app']);
    });

    it('fails, with a clear reason, a model recipient without an address in an address job', function (string $job): void {
        config()->set('campaigns.process-recipient-job', $job);
        config()->set('campaigns.notification', null);
        Mail::fake();
        Event::fake([RecipientFailed::class]);

        $campaign = Campaigns::create('Announcement', 'Hello')
            ->to(CampaignRecipient::forModel(PlainOwner::create(), 'Jane'))
            ->dispatch();

        workQueue();

        expect(Campaigns::find($campaign->uuid)->progress)
            ->status->toBe(CampaignStatus::Failed)
            ->failed->toBe(1);

        Event::assertDispatched(RecipientFailed::class, fn (RecipientFailed $event): bool => str_contains($event->error, 'no reachableAt address')
            && $event->recipient->hasModel());
    })->with([SendCampaignEmail::class, SendCampaignNotification::class]);

    it('mails a model recipient that has an address', function (): void {
        Mail::fake();

        $campaign = Campaigns::create('Announcement', 'Hello')
            ->to(CampaignRecipient::forModel(PlainOwner::create(), 'Jane', 'jane@doe.tld'))
            ->dispatch();

        workQueue();

        expect(Campaigns::find($campaign->uuid)->progress->sent)->toBe(1);
    });
});

describe('the fake', function (): void {
    beforeEach(function (): void {
        Bus::fake();
        $this->fake = Campaigns::fake();
    });

    it('records address and model recipients alike', function (): void {
        $jane = PlainOwner::create(['name' => 'Jane']);
        $john = PlainOwner::create(['name' => 'John']);

        $campaign = Campaigns::create('Announcement', 'Hello')
            ->to([CampaignRecipient::forModel($jane, 'Jane'), CampaignRecipient::forModel($john, 'John'), 'ops@shop.tld'])
            ->dispatch();

        [$janeRecipient, $johnRecipient, $ops] = Campaigns::campaign($campaign)->recipients()->all();

        Campaigns::campaign($campaign)->markProcessed($janeRecipient);
        Campaigns::campaign($campaign)->markProcessed($ops);
        Campaigns::campaign($campaign)->markFailed($johnRecipient, 'muted');

        $this->fake->assertCreated(fn (Campaign $created, array $recipients): bool => $recipients[0]->isFor($jane)
            && $recipients[2]->reachableAt === 'ops@shop.tld' && ! $recipients[2]->hasModel());
        $this->fake->assertRecipientProcessed($jane);
        $this->fake->assertRecipientProcessed($ops);
        $this->fake->assertRecipientFailed($john, 'muted');

        expect(fn () => $this->fake->assertRecipientProcessed($john))
            ->toThrow(AssertionFailedError::class, PlainOwner::class.':'.$john->id)
            ->and(fn () => $this->fake->assertRecipientFailed($jane))
            ->toThrow(AssertionFailedError::class);
    });
});
