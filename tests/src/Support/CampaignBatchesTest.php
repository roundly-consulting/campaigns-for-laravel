<?php

declare(strict_types=1);

use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Contracts\CampaignStore;
use RoundlyConsulting\Campaigns\Facades\Campaigns;
use RoundlyConsulting\Campaigns\Stores\InMemoryCampaignStore;
use RoundlyConsulting\Campaigns\Support\CampaignBatches;

beforeEach(function (): void {
    fakeBus();

    $this->campaign = Campaigns::prepare(
        new Campaign(uuid: '00000000-0000-4000-8000-c00000000001', subject: 'S', content: 'B', fromName: 'Shop', fromAddress: 'shop@shop.tld'),
        [new CampaignRecipient(uuid: '00000000-0000-4000-8000-00000000a001', name: 'Jane', reachableAt: 'jane@doe.tld')],
    );

    $this->batch = Campaigns::campaign($this->campaign)->batch();
});

it('falls back to the batch when the store never saw the recipients', function (): void {
    // The in-memory store of another process: three jobs, one still to run, one failed outright.
    $this->batch->totalJobs = 3;
    $this->batch->pendingJobs = 2;
    $this->batch->failedJobs = 1;

    expect(app(CampaignBatches::class)->syncProgress(clone $this->campaign, new InMemoryCampaignStore)->progress)
        ->total->toBe(3)
        ->sent->toBe(1)
        ->failed->toBe(1)
        ->pending->toBe(1);
});

it('never counts more failures than recipients left without a delivery', function (): void {
    // A job that failed once and then went through on a retry stays in the batch's failed count.
    $recipient = Campaigns::campaign($this->campaign)->recipient('00000000-0000-4000-8000-00000000a001');
    Campaigns::campaign($this->campaign)->markProcessed($recipient);
    $this->batch->failedJobs = 1;

    expect(app(CampaignBatches::class)->syncProgress(clone $this->campaign, app(CampaignStore::class))->progress)
        ->total->toBe(1)
        ->sent->toBe(1)
        ->failed->toBe(0)
        ->pending->toBe(0);
});
