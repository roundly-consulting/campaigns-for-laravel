<?php

declare(strict_types=1);

use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Facades\Campaigns;

it('cancels a campaign by uuid', function (): void {
    fakeBus();

    $campaign = Campaigns::create('Subject', 'Body')->to('a@a.tld')->dispatch();

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

it('reports a campaign that already ended without changing it', function (): void {
    fakeBus();

    $campaign = Campaigns::create('Subject', 'Body')->to('a@a.tld')->dispatch();
    finishBatch(Campaigns::campaign($campaign)->batch());

    $this->artisan('campaigns:cancel', ['uuid' => $campaign->uuid])
        ->expectsOutputToContain('already ended (Completed)')
        ->assertSuccessful();

    expect(Campaigns::find($campaign->uuid)->progress->status)->toBe(CampaignStatus::Completed);
});

it('is recorded by the fake', function (): void {
    $fake = Campaigns::fake();
    $campaign = Campaigns::create('Subject', 'Body')->prepare();

    $this->artisan('campaigns:cancel', ['uuid' => $campaign->uuid])->assertSuccessful();

    $fake->assertCancelled($campaign->uuid);
});
