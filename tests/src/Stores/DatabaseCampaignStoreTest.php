<?php

declare(strict_types=1);

use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Models\CampaignRecipientRecord;
use RoundlyConsulting\Campaigns\Models\CampaignRecord;
use RoundlyConsulting\Campaigns\Stores\DatabaseCampaignStore;

beforeEach(function (): void {
    $this->store = new DatabaseCampaignStore;
});

it('answers a non-uuid lookup with nothing, on every engine', function (): void {
    expect($this->store->find('not-a-uuid'))->toBeNull()
        ->and($this->store->findRecipient('not-a-uuid', '00000000-0000-4000-8000-00000000a001'))->toBeNull()
        ->and($this->store->findRecipient('00000000-0000-4000-8000-c00000000001', 'not-a-uuid'))->toBeNull()
        ->and($this->store->recipients('not-a-uuid'))->toHaveCount(0);
});

it('re-saves a soft-deleted campaign instead of inserting a duplicate uuid', function (): void {
    $record = CampaignRecord::factory()->create(['uuid' => '00000000-0000-4000-8000-c00000000001']);
    $record->delete();

    $this->store->save(new Campaign(
        uuid: '00000000-0000-4000-8000-c00000000001',
        subject: 'Again',
        content: 'Body',
        fromName: 'Shop',
        fromAddress: 'no-reply@shop.tld',
    ));

    expect(CampaignRecord::withTrashed()->where('uuid', '00000000-0000-4000-8000-c00000000001')->count())->toBe(1)
        ->and($this->store->find('00000000-0000-4000-8000-c00000000001'))->toBeNull();
});

it('re-saves a soft-deleted recipient instead of inserting a duplicate uuid', function (): void {
    $record = CampaignRecipientRecord::factory()->create([
        'uuid' => '00000000-0000-4000-8000-00000000a001',
        'campaign_uuid' => '00000000-0000-4000-8000-c00000000001',
    ]);
    $record->delete();

    $this->store->saveRecipients('00000000-0000-4000-8000-c00000000001', [
        new CampaignRecipient(uuid: '00000000-0000-4000-8000-00000000a001', name: 'John', reachableAt: 'john@doe.tld'),
    ]);

    expect(CampaignRecipientRecord::withTrashed()->where('uuid', '00000000-0000-4000-8000-00000000a001')->count())->toBe(1);
});
