<?php

declare(strict_types=1);

use Illuminate\Bus\Batch;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Managers\InMemoryManager;
use RoundlyConsulting\Campaigns\Managers\Manager;

beforeEach(function () {
    $this->manager = resolve(Manager::class);

    $this->campaigns = [
        new Campaign(
            uuid: '3b2d9cc2-a069-4284-a894-1c021c3bfcbb',
            subject: 'Testing Campaign',
            content: 'Hello',
            fromName: 'Unit Testing',
            fromAddress: 'unit@testing.com',
        ),
        new Campaign(
            uuid: 'd57e3d19-0af7-4ea6-9698-cc48a9db4ac8',
            subject: 'Another Campaign',
            content: 'Hi',
            fromName: 'Pest Testing',
            fromAddress: 'pest@testing.com',
        ),
        new Campaign(
            uuid: '761ef6e5-770b-41f3-a34b-8d7941220fdc',
            subject: 'Yet Another Campaign',
            content: 'Good Morning',
            fromName: 'Testing',
            fromAddress: 'hi@testing.com',
        ),
    ];
});

test('it finds campaign by uuid', function () {
    $this->manager
        ->addCampaign($this->campaigns[0]);

    expect($this->manager->find($this->campaigns[0]->uuid))
        ->toBeInstanceOf(Campaign::class)
        ->uuid->toBe('3b2d9cc2-a069-4284-a894-1c021c3bfcbb');
});

test('it executes callback on each campaign for offset and limit', function () {
    $this->manager
        ->addCampaign($this->campaigns[0])
        ->addCampaign($this->campaigns[1])
        ->addCampaign($this->campaigns[2]);

    $result = collect();

    $this->manager->onEachCampaign(
        callback: fn (Campaign $campaign) => $result->push($campaign),
        offset: 1,
        limit: 1,
    );

    expect($result)
        ->count()->toBe(1)
        ->first()->uuid->toBe('d57e3d19-0af7-4ea6-9698-cc48a9db4ac8');
});

test('it prepares campaign', function () {
    fakeBus();

    $this->manager->prepare($this->campaigns[0]);

    expect($this->manager->find($this->campaigns[0]->uuid))
        ->progress->status->value->toBe(CampaignStatus::Pending->value);
});

test('it starts processing campaign', function () {
    fakeBus();

    $this->manager->prepare($this->campaigns[0]);
    $this->manager->start($this->campaigns[0]->uuid);

    expect($this->manager->find($this->campaigns[0]->uuid))
        ->progress->status->value->toBe(CampaignStatus::Processing->value);
});

test('it cancels processing campaign', function () {
    fakeBus();

    $this->manager->prepare($this->campaigns[0]);
    $this->manager->start($this->campaigns[0]->uuid);

    /** @var Batch $batch */
    $batch = $this->manager->findBatchForCampaign($this->campaigns[0]);

    expect($this->manager->find($this->campaigns[0]->uuid))
        ->progress->status->value->toBe(CampaignStatus::Processing->value);

    expect($batch)
        ->cancelledAt->toBeNull()
        ->finishedAt->toBeNull();

    $this->manager->cancel($this->campaigns[0]->uuid);

    expect($this->manager->find($this->campaigns[0]->uuid))
        ->progress->status->value->toBe(CampaignStatus::Canceled->value);

    expect($batch)
        ->cancelledAt->not->toBeNull();
});

test('it adds recipients to campaign', function () {
    fakeBus();

    $this->manager->prepare($this->campaigns[0]);

    /** @var Campaign $campaign */
    $campaign = $this->manager->find($this->campaigns[0]->uuid);

    /** @var Batch $batch */
    $batch = $this->manager->findBatchForCampaign($campaign);

    expect($campaign)
        ->progress->status->value->toBe(CampaignStatus::Pending->value)
        ->and($batch)
        ->added->toHaveCount(0);

    $this->manager->pushRecipientsToCampaign(
        campaignUuid: $campaign->uuid,
        recipients: [
            new CampaignRecipient(
                uuid: '0e4a40f8-496f-4032-925f-8693f3dd149a',
                name: 'John Doe',
                reachableAt: 'john@doe.com',
            ),
            new CampaignRecipient(
                uuid: '418396ca-ef54-4df0-93ec-c270b845fc4c',
                name: 'Jane Doe',
                reachableAt: 'jane@doe.com',
            ),
        ],
    );

    expect(InMemoryManager::$recipients[$campaign->uuid])
        ->toHaveCount(2);
});

test('it marks recipient as processed', function () {
    fakeBus();

    $this->manager->prepare($this->campaigns[0]);

    /** @var Campaign $campaign */
    $campaign = $this->manager->find($this->campaigns[0]->uuid);

    $this->manager->pushRecipientsToCampaign(
        campaignUuid: $this->campaigns[0]->uuid,
        recipients: [
            $recipient = new CampaignRecipient(
                uuid: '0e4a40f8-496f-4032-925f-8693f3dd149a',
                name: 'John Doe',
                reachableAt: 'john@doe.com',
            ),
            new CampaignRecipient(
                uuid: '418396ca-ef54-4df0-93ec-c270b845fc4c',
                name: 'Jane Doe',
                reachableAt: 'jane@doe.com',
            ),
        ],
    );

    $this->manager->start($this->campaigns[0]->uuid);

    $this->manager->markRecipientAsProcessed($campaign, $recipient);

    expect($this->manager->findRecipient($campaign->uuid, $recipient->uuid))
        ->hasBeenProcessed
        ->toBeTrue()
        ->and($this->manager->findRecipient($campaign->uuid, '418396ca-ef54-4df0-93ec-c270b845fc4c'))
        ->hasBeenProcessed
        ->toBeFalse();
});

test('it marks recipient as failed with error message', function () {
    fakeBus();

    $this->manager->prepare($this->campaigns[0]);

    /** @var Campaign $campaign */
    $campaign = $this->manager->find($this->campaigns[0]->uuid);

    $this->manager->pushRecipientsToCampaign(
        campaignUuid: $campaign->uuid,
        recipients: [
            $recipient = new CampaignRecipient(
                uuid: '0e4a40f8-496f-4032-925f-8693f3dd149a',
                name: 'John Doe',
                reachableAt: 'john@doe.com',
            ),
        ],
    );

    $this->manager->markRecipientAsFailed($campaign, $recipient, 'Something happened');

    expect($this->manager->findRecipient($campaign->uuid, $recipient->uuid))
        ->hasBeenProcessed
        ->toBeFalse()
        ->errorOccured
        ->toBeTrue()
        ->errorMessage->toBe('Something happened');
});
