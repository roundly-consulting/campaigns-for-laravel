<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignProgress;
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
        progress: new CampaignProgress(
            status: CampaignStatus::Processing,
            sent: 10,
            pending: 23,
            total: 33,
        ),
        startedAt: Carbon::parse('2023-01-10 18:00'),
    ));

    $store->save(new Campaign(
        uuid: '5fd217ef-6815-421c-9576-a0fd123f8d6d',
        subject: 'Another one!',
        content: 'Heya',
        fromName: 'Unit Testing',
        fromAddress: 'unit@testing.tld',
        progress: new CampaignProgress(
            status: CampaignStatus::Completed,
            sent: 80,
            pending: 0,
            total: 80,
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
                    '0% (0 to be sent of 0)',
                    'N/A',
                    'N/A',
                ],
                [
                    'fc6aa8c0-79fa-420f-92a3-405167140616',
                    'Test',
                    'Unit Testing (unit@testing.tld)',
                    'Processing',
                    '30.3% (23 to be sent of 33)',
                    '2023-01-10 18:00',
                    'N/A',
                ],
                [
                    '5fd217ef-6815-421c-9576-a0fd123f8d6d',
                    'Another one!',
                    'Unit Testing (unit@testing.tld)',
                    'Completed',
                    '100% (0 to be sent of 80)',
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
                    '30.3% (23 to be sent of 33)',
                    '2023-01-10 18:00',
                    'N/A',
                ],
            ],
        )
        ->assertSuccessful();
});
