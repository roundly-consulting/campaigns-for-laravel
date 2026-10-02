<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignProgress;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Contracts\CampaignStore;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;

beforeEach(function () {
    $store = resolve(CampaignStore::class);

    $store->save(new Campaign(
        uuid: '851d62ce-9d90-475b-89f8-b14f56050dd8',
        subject: 'Unit',
        content: 'Hello',
        fromName: 'Unit Testing',
        fromAddress: 'unit@testing.tld',
    ));

    $store->save(new Campaign(
        uuid: 'fc6aa8c0-79fa-420f-92a3-405167140616',
        subject: 'Test',
        content: 'Hi',
        fromName: 'Unit Testing',
        fromAddress: 'unit@testing.tld',
        progress: new CampaignProgress(status: CampaignStatus::Processing),
        startedAt: Carbon::parse('2023-01-10 18:00'),
    ));

    // A sending campaign's progress is read live from its recipients: 10 sent, 2 failed of 33.
    $store->saveRecipients('fc6aa8c0-79fa-420f-92a3-405167140616', array_map(
        static fn (int $i): CampaignRecipient => new CampaignRecipient(
            uuid: sprintf('00000000-0000-4000-8000-%012d', $i),
            name: "Recipient {$i}",
            reachableAt: "r{$i}@testing.tld",
            hasBeenProcessed: $i <= 10,
            errorOccured: $i > 10 && $i <= 12,
        ),
        range(1, 33),
    ));

    $store->save(new Campaign(
        uuid: '5fd217ef-6815-421c-9576-a0fd123f8d6d',
        subject: 'Another one!',
        content: 'Heya',
        fromName: 'Unit Testing',
        fromAddress: 'unit@testing.tld',
        progress: new CampaignProgress(
            status: CampaignStatus::Completed,
            sent: 78,
            pending: 0,
            total: 80,
            failed: 2,
        ),
        startedAt: Carbon::parse('2023-01-01 05:00'),
        endedAt: Carbon::parse('2023-01-01 05:01'),
    ));
});

it('displays table of campaigns', function () {
    $this->artisan('campaigns:list')
        ->expectsTable(
            headers: ['ID', 'Subject', 'Sender', 'Status', 'Progress', 'Started at', 'Ended at'],
            rows: [
                [
                    '851d62ce-9d90-475b-89f8-b14f56050dd8',
                    'Unit',
                    'Unit Testing (unit@testing.tld)',
                    'Created',
                    '0% (0 sent, 0 failed, 0 to be sent of 0)',
                    'N/A',
                    'N/A',
                ],
                [
                    'fc6aa8c0-79fa-420f-92a3-405167140616',
                    'Test',
                    'Unit Testing (unit@testing.tld)',
                    'Processing',
                    '30.3% (10 sent, 2 failed, 21 to be sent of 33)',
                    '2023-01-10 18:00',
                    'N/A',
                ],
                [
                    '5fd217ef-6815-421c-9576-a0fd123f8d6d',
                    'Another one!',
                    'Unit Testing (unit@testing.tld)',
                    'Completed',
                    '97.5% (78 sent, 2 failed, 0 to be sent of 80)',
                    '2023-01-01 05:00',
                    '2023-01-01 05:01',
                ],
            ],
        )
        ->assertSuccessful();
});

it('displays table of campaigns for specific offset', function () {
    $this->artisan('campaigns:list --offset=1 --limit=1')
        ->expectsTable(
            headers: ['ID', 'Subject', 'Sender', 'Status', 'Progress', 'Started at', 'Ended at'],
            rows: [
                [
                    'fc6aa8c0-79fa-420f-92a3-405167140616',
                    'Test',
                    'Unit Testing (unit@testing.tld)',
                    'Processing',
                    '30.3% (10 sent, 2 failed, 21 to be sent of 33)',
                    '2023-01-10 18:00',
                    'N/A',
                ],
            ],
        )
        ->assertSuccessful();
});
