<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignProgress;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;

it('gives each campaign its own progress object', function (): void {
    $first = new Campaign(
        uuid: 'a',
        subject: 'A',
        content: 'A',
        fromName: 'A',
        fromAddress: 'a@a.tld',
    );

    $second = new Campaign(
        uuid: 'b',
        subject: 'B',
        content: 'B',
        fromName: 'B',
        fromAddress: 'b@b.tld',
    );

    $first->progress->sent = 5;

    expect($second->progress->sent)->toBe(0)
        ->and($first->progress)->not->toBe($second->progress);
});

it('serialises to an array', function (): void {
    $campaign = new Campaign(
        uuid: 'uuid-1',
        subject: 'Hi',
        content: '<p>Hi</p>',
        fromName: 'Shop',
        fromAddress: 'no-reply@shop.tld',
        progress: new CampaignProgress(status: CampaignStatus::Processing, sent: 1, pending: 1, total: 2),
        startedAt: Carbon::parse('2026-01-01 10:00:00'),
        endedAt: null,
        batch: 'batch-1',
    );

    expect($campaign->toArray())
        ->toMatchArray([
            'uuid' => 'uuid-1',
            'subject' => 'Hi',
            'content' => '<p>Hi</p>',
            'fromName' => 'Shop',
            'fromAddress' => 'no-reply@shop.tld',
            'endedAt' => null,
            'batch' => 'batch-1',
        ])
        ->and($campaign->toArray()['progress'])->toBe([
            'status' => 'Processing',
            'sent' => 1,
            'failed' => 0,
            'pending' => 1,
            'total' => 2,
            'remaining' => 1,
            'percentage' => 50.0,
        ])
        ->and($campaign->toArray()['startedAt'])->toContain('2026-01-01');
});

it('clones deeply so a copy shares no mutable state', function (): void {
    $campaign = new Campaign(
        uuid: 'uuid-1',
        subject: 'Hi',
        content: 'Hi',
        fromName: 'Shop',
        fromAddress: 'no-reply@shop.tld',
        startedAt: Carbon::parse('2026-01-01 10:00:00'),
        endedAt: Carbon::parse('2026-01-01 11:00:00'),
    );

    $copy = clone $campaign;
    $copy->progress->sent = 9;
    $copy->startedAt?->addDay();
    $copy->endedAt?->addDay();

    expect($campaign->progress->sent)->toBe(0)
        ->and($campaign->startedAt?->toDateTimeString())->toBe('2026-01-01 10:00:00')
        ->and($campaign->endedAt?->toDateTimeString())->toBe('2026-01-01 11:00:00');
});
