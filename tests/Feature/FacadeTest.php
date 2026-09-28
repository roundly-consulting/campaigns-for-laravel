<?php

declare(strict_types=1);

use Illuminate\Bus\Batch;
use Illuminate\Support\Collection;
use RoundlyConsulting\Campaigns\Actions\StartCampaignAction;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignHandle;
use RoundlyConsulting\Campaigns\CampaignManager;
use RoundlyConsulting\Campaigns\CampaignProgress;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Exceptions\CampaignNotFound;
use RoundlyConsulting\Campaigns\Exceptions\InvalidCampaignTransition;
use RoundlyConsulting\Campaigns\Exceptions\RecipientNotFound;
use RoundlyConsulting\Campaigns\Facades\Campaigns;
use RoundlyConsulting\Campaigns\PendingCampaign;
use RoundlyConsulting\Campaigns\Support\CampaignSettings;

beforeEach(fn () => fakeBus());

it('documents its root, is fakeable and reaches every action', function (): void {
    expect(Campaigns::class)
        ->toDocumentItsRoot()
        ->toBeFakeable()
        ->toReachEveryAction(__DIR__.'/../../src/Actions');
});

it('creates a fluent builder', function (): void {
    expect(Campaigns::create('Subject', 'Body'))->toBeInstanceOf(PendingCampaign::class);
});

it('prepares a campaign from a value object and recipients', function (string $store): void {
    useStore($store);

    $campaign = Campaigns::prepare(
        new Campaign(uuid: '00000000-0000-4000-8000-c00000000001', subject: 'S', content: 'B', fromName: 'Shop', fromAddress: 'no-reply@shop.tld'),
        collect([new CampaignRecipient(uuid: '00000000-0000-4000-8000-00000000a001', name: 'John', reachableAt: 'john@doe.tld')]),
    );

    expect($campaign->progress->status)->toBe(CampaignStatus::Pending)
        ->and(Campaigns::campaign($campaign)->recipients())->toHaveCount(1);
})->with('stores');

it('starts a prepared campaign by uuid or value object', function (string $store): void {
    useStore($store);

    $first = Campaigns::create('One', 'Body')->to('a@a.tld')->prepare();
    $second = Campaigns::create('Two', 'Body')->to('b@b.tld')->prepare();

    expect(Campaigns::start($first->uuid)->progress->status)->toBe(CampaignStatus::Processing)
        ->and(Campaigns::start($second)->progress->status)->toBe(CampaignStatus::Processing)
        ->and(Campaigns::find($first->uuid)->startedAt)->not->toBeNull();
})->with('stores');

it('refuses to start an unknown campaign', function (): void {
    expect(fn () => Campaigns::start('00000000-0000-4000-8000-c0000000ffff'))->toThrow(CampaignNotFound::class);
});

it('cancels a campaign by uuid or value object', function (string $store): void {
    useStore($store);

    $first = Campaigns::create('One', 'Body')->dispatch();
    $second = Campaigns::create('Two', 'Body')->dispatch();

    expect(Campaigns::cancel($first->uuid)->progress->status)->toBe(CampaignStatus::Canceled)
        ->and(Campaigns::cancel($second)->progress->status)->toBe(CampaignStatus::Canceled)
        ->and(fn () => Campaigns::cancel('00000000-0000-4000-8000-c0000000ffff'))->toThrow(CampaignNotFound::class);
})->with('stores');

it('finds campaigns, or fails', function (string $store): void {
    useStore($store);

    $campaign = Campaigns::create('One', 'Body')->prepare();

    expect(Campaigns::find($campaign->uuid)?->uuid)->toBe($campaign->uuid)
        ->and(Campaigns::find('00000000-0000-4000-8000-c0000000ffff'))->toBeNull()
        ->and(Campaigns::findOrFail($campaign->uuid)->subject)->toBe('One')
        ->and(fn () => Campaigns::findOrFail('00000000-0000-4000-8000-c0000000ffff'))->toThrow(CampaignNotFound::class);
})->with('stores');

it('pages all campaigns in creation order', function (string $store): void {
    useStore($store);

    $uuids = collect(['One', 'Two', 'Three'])
        ->map(fn (string $subject): string => Campaigns::create($subject, 'Body')->prepare()->uuid)
        ->all();

    expect(Campaigns::all())->toBeInstanceOf(Collection::class)
        ->and(Campaigns::all()->pluck('uuid')->all())->toBe($uuids)
        ->and(Campaigns::all(offset: 1, limit: 1)->pluck('uuid')->all())->toBe([$uuids[1]]);
})->with('stores');

it('reads the send settings', function (): void {
    config()->set('campaigns.batch-queue', 'blasts');

    expect(Campaigns::settings())->toBeInstanceOf(CampaignSettings::class)
        ->and(Campaigns::settings()->batchQueue())->toBe('blasts');
});

describe('campaign handle', function (): void {
    it('is scoped to one campaign', function (string $store): void {
        useStore($store);

        $campaign = Campaigns::create('One', 'Body')->to(['a@a.tld', 'b@b.tld'])->prepare();
        $handle = Campaigns::campaign($campaign->uuid);

        expect($handle)->toBeInstanceOf(CampaignHandle::class)
            ->and($handle->uuid())->toBe($campaign->uuid)
            ->and($handle->get()->subject)->toBe('One')
            ->and($handle->progress())->toBeInstanceOf(CampaignProgress::class)
            ->and($handle->progress()->status)->toBe(CampaignStatus::Pending)
            ->and($handle->recipients())->toHaveCount(2)
            ->and($handle->recipients(offset: 1)->pluck('reachableAt')->all())->toBe(['b@b.tld'])
            ->and($handle->recipients(limit: 1)->pluck('reachableAt')->all())->toBe(['a@a.tld'])
            ->and($handle->batch())->toBeInstanceOf(Batch::class)
            ->and($handle->batch()?->id)->toBe($campaign->batch);
    })->with('stores');

    it('reads one recipient by uuid or value object', function (string $store): void {
        useStore($store);

        $campaign = Campaigns::create('One', 'Body')->to('a@a.tld')->prepare();
        $recipient = Campaigns::campaign($campaign)->recipients()->first();

        expect(Campaigns::campaign($campaign)->recipient($recipient->uuid)->reachableAt)->toBe('a@a.tld')
            ->and(Campaigns::campaign($campaign)->recipient($recipient)->campaignUuid)->toBe($campaign->uuid);
    })->with('stores');

    it('starts and cancels', function (string $store): void {
        useStore($store);

        $campaign = Campaigns::create('One', 'Body')->to('a@a.tld')->prepare();

        expect(Campaigns::campaign($campaign)->start()->progress->status)->toBe(CampaignStatus::Processing)
            ->and(Campaigns::campaign($campaign)->cancel()->progress->status)->toBe(CampaignStatus::Canceled);
    })->with('stores');

    it('records delivery outcomes', function (string $store): void {
        useStore($store);

        $campaign = Campaigns::create('One', 'Body')->to(['a@a.tld', 'b@b.tld'])->dispatch();
        [$delivered, $bounced] = Campaigns::campaign($campaign)->recipients()->all();

        Campaigns::campaign($campaign)->markProcessed($delivered);
        Campaigns::campaign($campaign)->markFailed($bounced, 'mailbox full');

        expect(Campaigns::campaign($campaign)->recipient($delivered)->hasBeenProcessed)->toBeTrue()
            ->and(Campaigns::campaign($campaign)->recipient($bounced))
            ->errorOccured->toBeTrue()
            ->errorMessage->toBe('mailbox full');
    })->with('stores');

    it('has no batch before a campaign is prepared', function (): void {
        $handle = Campaigns::campaign(new Campaign(uuid: 'unsaved', subject: 'S', content: 'B', fromName: 'n', fromAddress: 'a@a.tld'));

        expect($handle->batch())->toBeNull()
            ->and($handle->recipients())->toHaveCount(0)
            ->and(fn () => $handle->progress())->toThrow(CampaignNotFound::class);
    });

    it('refuses an unknown campaign uuid', function (): void {
        expect(fn () => Campaigns::campaign('00000000-0000-4000-8000-c0000000ffff'))->toThrow(CampaignNotFound::class);
    });
});

describe('cross-campaign refusals', function (): void {
    beforeEach(function (): void {
        $this->a = Campaigns::create('A', 'Body')->to('a@a.tld')->dispatch();
        $this->b = Campaigns::create('B', 'Body')->to('b@b.tld')->dispatch();
        $this->recipientOfB = Campaigns::campaign($this->b)->recipients()->first();
    });

    it('never reads a recipient of another campaign', function (): void {
        expect(fn () => Campaigns::campaign($this->a)->recipient($this->recipientOfB))
            ->toThrow(RecipientNotFound::class, $this->recipientOfB->uuid)
            ->and(fn () => Campaigns::campaign($this->a)->recipient($this->recipientOfB->uuid))
            ->toThrow(RecipientNotFound::class);
    });

    it('never marks a recipient of another campaign', function (): void {
        expect(fn () => Campaigns::campaign($this->a)->markProcessed($this->recipientOfB))->toThrow(RecipientNotFound::class)
            ->and(fn () => Campaigns::campaign($this->a)->markFailed($this->recipientOfB, 'x'))->toThrow(RecipientNotFound::class)
            ->and(Campaigns::campaign($this->b)->recipient($this->recipientOfB)->hasBeenProcessed)->toBeFalse();
    });

    it('never marks a recipient that belongs to no campaign', function (): void {
        $loose = new CampaignRecipient(uuid: 'loose', name: 'Loose', reachableAt: 'loose@doe.tld');

        expect(fn () => Campaigns::campaign($this->a)->markProcessed($loose))->toThrow(RecipientNotFound::class);
    });
});

it('refuses to start a campaign that is not pending', function (): void {
    $campaign = Campaigns::create('One', 'Body')->to('a@a.tld')->dispatch();

    expect(fn () => Campaigns::start($campaign->uuid))
        ->toThrow(InvalidCampaignTransition::class, 'Processing');
});

it('serves the same API to an injected manager', function (): void {
    $manager = app(CampaignManager::class);

    $campaign = $manager->create('Injected', 'Body')->to('a@a.tld')->prepare();
    $started = $manager->start($campaign->uuid);

    expect($manager)->toBe(Campaigns::getFacadeRoot())
        ->and($started->progress->status)->toBe(CampaignStatus::Processing)
        ->and($manager->campaign($campaign->uuid)->recipients())->toHaveCount(1)
        ->and($manager->all())->toHaveCount(1);
});

it('lets the raw action start a campaign the facade prepared', function (): void {
    $campaign = Campaigns::create('Raw', 'Body')->to('a@a.tld')->prepare();

    expect(app(StartCampaignAction::class)->execute($campaign->uuid)->progress->status)->toBe(CampaignStatus::Processing);
});
