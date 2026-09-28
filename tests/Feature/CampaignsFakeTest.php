<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignManager;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Contracts\CampaignStore;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Events\CampaignCancelled;
use RoundlyConsulting\Campaigns\Events\CampaignCompleted;
use RoundlyConsulting\Campaigns\Events\CampaignFailed;
use RoundlyConsulting\Campaigns\Events\CampaignPrepared;
use RoundlyConsulting\Campaigns\Events\CampaignStarted;
use RoundlyConsulting\Campaigns\Events\RecipientFailed;
use RoundlyConsulting\Campaigns\Events\RecipientProcessed;
use RoundlyConsulting\Campaigns\Exceptions\CampaignNotFound;
use RoundlyConsulting\Campaigns\Exceptions\InvalidCampaignTransition;
use RoundlyConsulting\Campaigns\Exceptions\RecipientNotFound;
use RoundlyConsulting\Campaigns\Facades\Campaigns;
use RoundlyConsulting\Campaigns\Jobs\SendCampaignEmail;
use RoundlyConsulting\Campaigns\Testing\CampaignsFake;

beforeEach(function (): void {
    Bus::fake();
    Event::fake();

    $this->fake = Campaigns::fake();
});

it('swaps a manager subtype into the facade and the container', function (): void {
    expect($this->fake)->toBeInstanceOf(CampaignsFake::class)
        ->toBeInstanceOf(CampaignManager::class)
        ->and(app(CampaignManager::class))->toBe($this->fake)
        ->and(Campaigns::getFacadeRoot())->toBe($this->fake);
});

it('runs nothing: no batch, no event, no write to the configured store', function (): void {
    $campaign = Campaigns::create('Spring sale', 'Body')->to('a@a.tld')->dispatch();

    Bus::assertNothingBatched();

    foreach ([CampaignPrepared::class, CampaignStarted::class, CampaignCompleted::class, CampaignFailed::class, CampaignCancelled::class, RecipientProcessed::class, RecipientFailed::class] as $event) {
        Event::assertNotDispatched($event);
    }

    expect(resolve(CampaignStore::class)->find($campaign->uuid))->toBeNull()
        ->and($campaign->batch)->toBeNull();
});

it('answers reads from what the test created', function (): void {
    $campaign = Campaigns::create('Spring sale', 'Body')->to(['a@a.tld', 'b@b.tld'])->prepare();

    expect(Campaigns::find($campaign->uuid)?->progress->status)->toBe(CampaignStatus::Pending)
        ->and(Campaigns::all())->toHaveCount(1)
        ->and(Campaigns::campaign($campaign)->recipients())->toHaveCount(2)
        ->and(Campaigns::campaign($campaign)->batch())->toBeNull();

    Campaigns::start($campaign);
    expect(Campaigns::campaign($campaign)->progress()->status)->toBe(CampaignStatus::Processing);

    Campaigns::cancel($campaign);
    expect(Campaigns::find($campaign->uuid)?->endedAt)->not->toBeNull();
});

it('refuses what the real manager refuses', function (): void {
    $campaign = Campaigns::create('One', 'Body')->to('a@a.tld')->dispatch();
    $foreign = new CampaignRecipient(uuid: 'r-b', name: 'B', reachableAt: 'b@doe.tld', campaignUuid: 'campaign-b');

    expect(fn () => Campaigns::start('missing'))->toThrow(CampaignNotFound::class)
        ->and(fn () => Campaigns::cancel('missing'))->toThrow(CampaignNotFound::class)
        ->and(fn () => Campaigns::start($campaign))->toThrow(InvalidCampaignTransition::class)
        ->and(fn () => $this->fake->markRecipientProcessed($campaign, $foreign))->toThrow(RecipientNotFound::class)
        ->and(fn () => $this->fake->markRecipientFailed($campaign, $foreign, 'x'))->toThrow(RecipientNotFound::class);

    $this->fake->assertStarted($campaign);
    expect($this->fake->findOrFail($campaign->uuid)->progress->status)->toBe(CampaignStatus::Processing);
});

it('leaves an ended campaign as it is when cancelled again, but records the call', function (): void {
    $campaign = Campaigns::create('One', 'Body')->prepare();
    Campaigns::cancel($campaign);
    $endedAt = Campaigns::find($campaign->uuid)?->endedAt;

    Campaigns::cancel($campaign);

    expect(Campaigns::find($campaign->uuid)?->endedAt?->eq($endedAt))->toBeTrue();
    $this->fake->assertCancelled($campaign);
});

describe('assertCreated', function (): void {
    it('passes for the builder and for prepare()', function (): void {
        Campaigns::create('Spring sale', 'Body')->to(['a@a.tld', 'b@b.tld'])->prepare();
        Campaigns::prepare(new Campaign(uuid: 'vo', subject: 'Direct', content: 'B', fromName: 'n', fromAddress: 'a@a.tld'));

        Campaigns::assertCreated();
        Campaigns::assertCreated(fn (Campaign $campaign, array $recipients): bool => $campaign->subject === 'Spring sale' && count($recipients) === 2);
        Campaigns::assertCreated(fn (Campaign $campaign): bool => $campaign->uuid === 'vo');
    });

    it('fails when nothing matches', function (): void {
        Campaigns::create('Spring sale', 'Body')->prepare();

        expect(fn () => Campaigns::assertCreated(fn (Campaign $campaign): bool => $campaign->subject === 'Winter'))
            ->toThrow(AssertionFailedError::class, 'matching campaign to be created');
    });

    it('fails when nothing was created', function (): void {
        expect(fn () => Campaigns::assertCreated())->toThrow(AssertionFailedError::class, 'none was');
    });

    it('asserts nothing created', function (): void {
        Campaigns::assertNothingCreated();

        Campaigns::create('One', 'Body')->prepare();

        expect(fn () => Campaigns::assertNothingCreated())->toThrow(AssertionFailedError::class, '1 were');
    });
});

describe('assertDispatched', function (): void {
    it('passes for dispatch() and for prepare() then start()', function (): void {
        Campaigns::create('Dispatched', 'Body')->dispatch();
        $later = Campaigns::create('Later', 'Body')->prepare();
        Campaigns::campaign($later)->start();

        Campaigns::assertDispatched();
        Campaigns::assertDispatched(fn (Campaign $campaign): bool => $campaign->subject === 'Dispatched');
        Campaigns::assertDispatched(fn (Campaign $campaign): bool => $campaign->subject === 'Later');
    });

    it('fails for a campaign that was only prepared', function (): void {
        Campaigns::create('Prepared', 'Body')->prepare();

        expect(fn () => Campaigns::assertDispatched())->toThrow(AssertionFailedError::class, 'none was')
            ->and(fn () => Campaigns::assertDispatched(fn (Campaign $campaign): bool => $campaign->subject === 'Prepared'))
            ->toThrow(AssertionFailedError::class, 'matching campaign to be dispatched');
    });

    it('asserts nothing dispatched', function (): void {
        Campaigns::create('Prepared', 'Body')->prepare();
        Campaigns::assertNothingDispatched();

        Campaigns::create('Dispatched', 'Body')->dispatch();

        expect(fn () => Campaigns::assertNothingDispatched())->toThrow(AssertionFailedError::class, '1 were');
    });
});

describe('assertStarted', function (): void {
    it('passes for a started campaign by uuid, value object or any', function (): void {
        $campaign = Campaigns::create('One', 'Body')->dispatch();

        Campaigns::assertStarted();
        Campaigns::assertStarted($campaign->uuid);
        Campaigns::assertStarted($campaign);
    });

    it('fails for another campaign or when none started', function (): void {
        expect(fn () => Campaigns::assertStarted())->toThrow(AssertionFailedError::class, 'none was');

        Campaigns::create('One', 'Body')->dispatch();

        expect(fn () => Campaigns::assertStarted('other'))->toThrow(AssertionFailedError::class, '[other] to be started');
    });

    it('asserts nothing started', function (): void {
        Campaigns::create('One', 'Body')->prepare();
        Campaigns::assertNothingStarted();

        Campaigns::create('Two', 'Body')->dispatch();

        expect(fn () => Campaigns::assertNothingStarted())->toThrow(AssertionFailedError::class, '1 were');
    });
});

describe('assertCancelled', function (): void {
    it('passes for a campaign cancelled through the facade or a handle', function (): void {
        $one = Campaigns::create('One', 'Body')->dispatch();
        $two = Campaigns::create('Two', 'Body')->dispatch();

        Campaigns::cancel($one->uuid);
        Campaigns::campaign($two)->cancel();

        Campaigns::assertCancelled();
        Campaigns::assertCancelled($one);
        Campaigns::assertCancelled($two->uuid);
    });

    it('fails for another campaign or when none was cancelled', function (): void {
        expect(fn () => Campaigns::assertCancelled())->toThrow(AssertionFailedError::class, 'none was');

        $one = Campaigns::create('One', 'Body')->dispatch();
        Campaigns::cancel($one);

        expect(fn () => Campaigns::assertCancelled('other'))->toThrow(AssertionFailedError::class, '[other] to be cancelled');
    });

    it('asserts nothing cancelled', function (): void {
        $one = Campaigns::create('One', 'Body')->dispatch();
        Campaigns::assertNothingCancelled();

        Campaigns::cancel($one);

        expect(fn () => Campaigns::assertNothingCancelled())->toThrow(AssertionFailedError::class, '1 were');
    });
});

describe('recipient outcomes', function (): void {
    beforeEach(function (): void {
        $this->campaign = Campaigns::create('One', 'Body')->from('no-reply@shop.tld')->to(['a@a.tld', 'b@b.tld'])->dispatch();
        [$this->first, $this->second] = Campaigns::campaign($this->campaign)->recipients()->all();
    });

    it('records a delivery job marking its recipient processed', function (): void {
        $job = new SendCampaignEmail($this->campaign, $this->first);
        $job->withFakeBatch();
        $job->handle(app(CampaignManager::class));

        Campaigns::assertRecipientProcessed();
        Campaigns::assertRecipientProcessed($this->first);
        Campaigns::assertRecipientProcessed($this->first->uuid);

        expect(Campaigns::campaign($this->campaign)->recipient($this->first)->hasBeenProcessed)->toBeTrue()
            ->and(fn () => Campaigns::assertRecipientProcessed($this->second))
            ->toThrow(AssertionFailedError::class, 'to be marked processed');
    });

    it('fails assertRecipientProcessed when nothing was processed', function (): void {
        expect(fn () => Campaigns::assertRecipientProcessed())->toThrow(AssertionFailedError::class, 'none was');
    });

    it('asserts nothing processed', function (): void {
        Campaigns::assertNothingProcessed();

        Campaigns::campaign($this->campaign)->markProcessed($this->first);

        expect(fn () => Campaigns::assertNothingProcessed())->toThrow(AssertionFailedError::class, '1 were');
    });

    it('records a failed delivery with its error', function (): void {
        Campaigns::campaign($this->campaign)->markFailed($this->second, 'mailbox full');

        Campaigns::assertRecipientFailed();
        Campaigns::assertRecipientFailed($this->second);
        Campaigns::assertRecipientFailed($this->second->uuid, 'mailbox full');

        expect(Campaigns::campaign($this->campaign)->recipient($this->second)->errorMessage)->toBe('mailbox full')
            ->and(fn () => Campaigns::assertRecipientFailed($this->second, 'other error'))
            ->toThrow(AssertionFailedError::class, 'with [other error]')
            ->and(fn () => Campaigns::assertRecipientFailed($this->first))
            ->toThrow(AssertionFailedError::class, 'to be marked failed');
    });

    it('fails assertRecipientFailed when nothing failed', function (): void {
        expect(fn () => Campaigns::assertRecipientFailed())->toThrow(AssertionFailedError::class, 'Expected a recipient to be marked failed');
    });

    it('asserts nothing failed', function (): void {
        Campaigns::assertNothingFailed();

        Campaigns::campaign($this->campaign)->markFailed($this->second, 'boom');

        expect(fn () => Campaigns::assertNothingFailed())->toThrow(AssertionFailedError::class, '1 were');
    });
});

it('records calls made through an injected manager', function (): void {
    $manager = app(CampaignManager::class);

    $campaign = $manager->create('Injected', 'Body')->dispatch();
    $manager->cancel($campaign);

    $this->fake->assertDispatched(fn (Campaign $recorded): bool => $recorded->subject === 'Injected');
    $this->fake->assertCancelled($campaign);
});
