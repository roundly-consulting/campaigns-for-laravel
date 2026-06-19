<?php

declare(strict_types=1);

use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Facades\Campaigns;

it('cancels a campaign by uuid', function (): void {
    fakeBus();

    $campaign = Campaigns::create('Subject', 'Body')->dispatch();

    $this->artisan('campaigns:cancel', ['uuid' => $campaign->uuid])
        ->expectsOutputToContain('cancelled')
        ->assertSuccessful();

    expect(Campaigns::find($campaign->uuid)->progress->status)->toBe(CampaignStatus::Canceled);
});

it('fails with a clear error for an unknown uuid', function (): void {
    $this->artisan('campaigns:cancel', ['uuid' => 'missing'])
        ->expectsOutputToContain('missing')
        ->assertFailed();
});
