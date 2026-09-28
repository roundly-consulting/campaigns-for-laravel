<?php

declare(strict_types=1);

use Illuminate\Bus\BatchRepository;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Campaigns\Actions\CancelCampaignAction;
use RoundlyConsulting\Campaigns\Actions\ChangeCampaignStatusAction;
use RoundlyConsulting\Campaigns\Actions\MarkRecipientFailedAction;
use RoundlyConsulting\Campaigns\Actions\MarkRecipientProcessedAction;
use RoundlyConsulting\Campaigns\Actions\PrepareCampaignAction;
use RoundlyConsulting\Campaigns\Actions\StartCampaignAction;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Contracts\CampaignStore;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Events\CampaignCompleted;
use RoundlyConsulting\Campaigns\Exceptions\CampaignNotFound;
use RoundlyConsulting\Campaigns\Exceptions\RecipientNotFound;
use RoundlyConsulting\Campaigns\Jobs\SendCampaignEmail;
use RoundlyConsulting\Campaigns\Jobs\SendCampaignNotification;

/**
 * Each action used raw — the third way into the API, next to the facade and the injected
 * manager.
 */
beforeEach(function (): void {
    fakeBus();

    $this->campaign = new Campaign(
        uuid: '00000000-0000-4000-8000-c00000000001',
        subject: 'Raw',
        content: 'Body',
        fromName: 'Shop',
        fromAddress: 'no-reply@shop.tld',
    );

    $this->recipient = new CampaignRecipient(uuid: '00000000-0000-4000-8000-00000000a001', name: 'John', reachableAt: 'john@doe.tld');
});

it('prepares a campaign with PrepareCampaignAction', function (): void {
    $campaign = app(PrepareCampaignAction::class)->execute($this->campaign, [$this->recipient]);

    expect($campaign->progress->status)->toBe(CampaignStatus::Pending)
        ->and($campaign->batch)->not->toBeNull()
        ->and(resolve(CampaignStore::class)->recipients($campaign->uuid))->toHaveCount(1)
        ->and($this->campaign->batch)->toBeNull()
        ->and($this->recipient->campaignUuid)->toBeNull();
});

it('resets the lifecycle of a campaign handed to PrepareCampaignAction', function (): void {
    $this->campaign->progress->status = CampaignStatus::Completed;
    $this->campaign->batch = 'stale';

    expect(app(PrepareCampaignAction::class)->execute($this->campaign))
        ->progress->status->toBe(CampaignStatus::Pending)
        ->startedAt->toBeNull()
        ->endedAt->toBeNull()
        ->batch->not->toBe('stale');
});

it('starts a prepared campaign with StartCampaignAction', function (): void {
    app(PrepareCampaignAction::class)->execute($this->campaign, [$this->recipient]);

    $campaign = app(StartCampaignAction::class)->execute($this->campaign->uuid);

    expect($campaign->progress->status)->toBe(CampaignStatus::Processing)
        ->and($campaign->progress->total)->toBe(1)
        ->and(resolve(BatchRepository::class)->find($campaign->batch)->added)
        ->toHaveCount(1)
        ->each->toBeInstanceOf(SendCampaignEmail::class);
});

it('queues the configured recipient job', function (): void {
    config()->set('campaigns.process-recipient-job', SendCampaignNotification::class);

    $campaign = app(PrepareCampaignAction::class)->execute($this->campaign, [$this->recipient]);
    app(StartCampaignAction::class)->execute($campaign);

    expect(resolve(BatchRepository::class)->find($campaign->batch)->added[0])
        ->toBeInstanceOf(SendCampaignNotification::class);
});

it('cancels a campaign with CancelCampaignAction', function (): void {
    app(PrepareCampaignAction::class)->execute($this->campaign);

    $campaign = app(CancelCampaignAction::class)->execute($this->campaign);

    expect($campaign->progress->status)->toBe(CampaignStatus::Canceled)
        ->and(fn () => app(CancelCampaignAction::class)->execute('00000000-0000-4000-8000-c0000000ffff'))
        ->toThrow(CampaignNotFound::class);
});

it('moves a campaign through ChangeCampaignStatusAction', function (): void {
    app(PrepareCampaignAction::class)->execute($this->campaign);

    Event::fake([CampaignCompleted::class]);

    $campaign = app(ChangeCampaignStatusAction::class)->execute($this->campaign, CampaignStatus::Completed);

    expect($campaign->progress->status)->toBe(CampaignStatus::Completed)
        ->and(resolve(CampaignStore::class)->find($this->campaign->uuid)->progress->status)->toBe(CampaignStatus::Completed);
    Event::assertDispatched(CampaignCompleted::class);
});

it('fires the event but writes nothing for a campaign the store no longer holds', function (): void {
    Event::fake([CampaignCompleted::class]);

    $campaign = app(ChangeCampaignStatusAction::class)->execute($this->campaign, CampaignStatus::Completed);

    expect($campaign->progress->status)->toBe(CampaignStatus::Completed)
        ->and(resolve(CampaignStore::class)->find($this->campaign->uuid))->toBeNull();
    Event::assertDispatched(CampaignCompleted::class);
});

it('records a delivery with MarkRecipientProcessedAction', function (): void {
    $campaign = app(PrepareCampaignAction::class)->execute($this->campaign, [$this->recipient]);
    $recipient = resolve(CampaignStore::class)->recipients($campaign->uuid)->first();
    $recipient->errorOccured = true;
    $recipient->errorMessage = 'earlier';

    app(MarkRecipientProcessedAction::class)->execute($campaign, $recipient);

    expect(resolve(CampaignStore::class)->findRecipient($campaign->uuid, $recipient->uuid))
        ->hasBeenProcessed->toBeTrue()
        ->errorOccured->toBeFalse()
        ->errorMessage->toBeNull();
});

it('records a failure with MarkRecipientFailedAction', function (): void {
    $campaign = app(PrepareCampaignAction::class)->execute($this->campaign, [$this->recipient]);
    $recipient = resolve(CampaignStore::class)->recipients($campaign->uuid)->first();

    app(MarkRecipientFailedAction::class)->execute($campaign, $recipient, 'bounced');

    expect(resolve(CampaignStore::class)->findRecipient($campaign->uuid, $recipient->uuid))
        ->hasBeenProcessed->toBeFalse()
        ->errorOccured->toBeTrue()
        ->errorMessage->toBe('bounced');
});

it('refuses to mark a recipient of another campaign, raw', function (): void {
    $campaign = app(PrepareCampaignAction::class)->execute($this->campaign);
    $foreign = new CampaignRecipient(uuid: 'r-b', name: 'B', reachableAt: 'b@doe.tld', campaignUuid: 'campaign-b');

    expect(fn () => app(MarkRecipientProcessedAction::class)->execute($campaign, $foreign))->toThrow(RecipientNotFound::class)
        ->and(fn () => app(MarkRecipientFailedAction::class)->execute($campaign, $foreign, 'x'))->toThrow(RecipientNotFound::class)
        ->and(fn () => app(MarkRecipientProcessedAction::class)->execute($campaign, $this->recipient))->toThrow(RecipientNotFound::class);
});

it('fires no event for a move back to Created', function (): void {
    app(PrepareCampaignAction::class)->execute($this->campaign);

    Event::fake();

    expect(app(ChangeCampaignStatusAction::class)->execute($this->campaign, CampaignStatus::Created)->progress->status)
        ->toBe(CampaignStatus::Created);
    Event::assertNothingDispatched();
});
