<?php

declare(strict_types=1);

use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Models\CampaignRecipientRecord;
use RoundlyConsulting\Campaigns\Models\CampaignRecord;

it('casts the status to the enum', function (): void {
    $record = CampaignRecord::factory()->create(['status' => CampaignStatus::Processing]);

    expect($record->fresh()->status)->toBe(CampaignStatus::Processing);
});

it('soft deletes a campaign record', function (): void {
    $record = CampaignRecord::factory()->create();

    $record->delete();

    expect(CampaignRecord::query()->count())->toBe(0)
        ->and(CampaignRecord::withTrashed()->count())->toBe(1);
});

it('relates recipients to a campaign', function (): void {
    $campaign = CampaignRecord::factory()->create(['uuid' => '00000000-0000-4000-8000-0000000000e1']);
    CampaignRecipientRecord::factory()->create(['campaign_uuid' => '00000000-0000-4000-8000-0000000000e1']);

    expect($campaign->recipients)->toHaveCount(1)
        ->and($campaign->recipients->first()->campaign->uuid)->toBe('00000000-0000-4000-8000-0000000000e1');
});

it('casts recipient booleans', function (): void {
    $recipient = CampaignRecipientRecord::factory()->create([
        'has_been_processed' => 1,
        'error_occured' => 0,
    ]);

    expect($recipient->fresh())
        ->has_been_processed->toBeTrue()
        ->error_occured->toBeFalse();
});
