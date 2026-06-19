<?php

declare(strict_types=1);

use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Managers\DatabaseManager;
use RoundlyConsulting\Campaigns\Models\CampaignRecord;

beforeEach(function (): void {
    $this->manager = new DatabaseManager;
});

it('returns null batch for a campaign without a batch id', function (): void {
    $campaign = new Campaign(
        uuid: 'no-batch',
        subject: 'No batch',
        content: 'Hello',
        fromName: 'Testing',
        fromAddress: 'test@testing.tld',
    );

    expect($this->manager->findBatchForCampaign($campaign))->toBeNull();
});

it('completes a campaign through the batch finally callback', function (): void {
    fakeBus();

    $this->manager->prepare(new Campaign(
        uuid: 'db-1',
        subject: 'DB',
        content: 'Body',
        fromName: 'Shop',
        fromAddress: 'no-reply@shop.tld',
    ));

    $batch = $this->manager->findBatchForCampaign($this->manager->findOrFail('db-1'));

    foreach ($batch->options['finally'] as $callback) {
        $callback($batch);
    }

    expect($this->manager->find('db-1')->progress->status)->toBe(CampaignStatus::Completed);
});

it('ignores a status change when the campaign record is gone', function (): void {
    fakeBus();

    $this->manager->prepare(new Campaign(
        uuid: 'db-2',
        subject: 'DB',
        content: 'Body',
        fromName: 'Shop',
        fromAddress: 'no-reply@shop.tld',
    ));

    $batch = $this->manager->findBatchForCampaign($this->manager->findOrFail('db-2'));

    CampaignRecord::query()->where('uuid', 'db-2')->forceDelete();

    foreach ($batch->options['finally'] as $callback) {
        $callback($batch);
    }

    expect($this->manager->find('db-2'))->toBeNull();
});

it('does not change a campaign already in the target status', function (): void {
    $record = CampaignRecord::factory()->create([
        'uuid' => 'db-3',
        'status' => CampaignStatus::Canceled,
    ]);

    $this->manager->cancel('db-3');

    expect($record->fresh()->ended_at)->toBeNull();
});
