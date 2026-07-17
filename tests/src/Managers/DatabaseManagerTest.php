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
        uuid: '00000000-0000-4000-8000-0000000000d1',
        subject: 'DB',
        content: 'Body',
        fromName: 'Shop',
        fromAddress: 'no-reply@shop.tld',
    ));

    $batch = $this->manager->findBatchForCampaign($this->manager->findOrFail('00000000-0000-4000-8000-0000000000d1'));

    foreach ($batch->options['finally'] as $callback) {
        $callback($batch);
    }

    expect($this->manager->find('00000000-0000-4000-8000-0000000000d1')->progress->status)->toBe(CampaignStatus::Completed);
});

it('ignores a status change when the campaign record is gone', function (): void {
    fakeBus();

    $this->manager->prepare(new Campaign(
        uuid: '00000000-0000-4000-8000-0000000000d2',
        subject: 'DB',
        content: 'Body',
        fromName: 'Shop',
        fromAddress: 'no-reply@shop.tld',
    ));

    $batch = $this->manager->findBatchForCampaign($this->manager->findOrFail('00000000-0000-4000-8000-0000000000d2'));

    CampaignRecord::query()->where('uuid', '00000000-0000-4000-8000-0000000000d2')->forceDelete();

    foreach ($batch->options['finally'] as $callback) {
        $callback($batch);
    }

    expect($this->manager->find('00000000-0000-4000-8000-0000000000d2'))->toBeNull();
});

it('does not change a campaign already in the target status', function (): void {
    $record = CampaignRecord::factory()->create([
        'uuid' => '00000000-0000-4000-8000-0000000000d3',
        'status' => CampaignStatus::Canceled,
    ]);

    $this->manager->cancel('00000000-0000-4000-8000-0000000000d3');

    expect($record->fresh()->ended_at)->toBeNull();
});
