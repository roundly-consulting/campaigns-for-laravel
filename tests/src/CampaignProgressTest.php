<?php

declare(strict_types=1);

use RoundlyConsulting\Campaigns\CampaignProgress;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;

it('calculates percentage of campaign progress', function () {
    $progress = [
        new CampaignProgress(
            sent: 0,
            total: 0,
        ),
        new CampaignProgress(
            sent: 5,
            total: 20,
        ),
        new CampaignProgress(
            sent: 10,
            total: 20,
        ),
        new CampaignProgress(
            sent: 20,
            total: 20,
        ),
        new CampaignProgress(
            sent: 13,
            total: 44,
        ),
    ];

    expect($progress[0]->percentage())->toBe(0.0);
    expect($progress[1]->percentage())->toBe(25.0);
    expect($progress[2]->percentage())->toBe(50.0);
    expect($progress[3]->percentage())->toBe(100.0);
    expect($progress[4]->percentage())->toBe(29.55);
});

it('reports running and complete predicates', function (): void {
    expect((new CampaignProgress(status: CampaignStatus::Processing))->isRunning())->toBeTrue()
        ->and((new CampaignProgress(status: CampaignStatus::Processing))->isComplete())->toBeFalse()
        ->and((new CampaignProgress(status: CampaignStatus::Completed))->isComplete())->toBeTrue()
        ->and((new CampaignProgress(status: CampaignStatus::Completed))->isRunning())->toBeFalse();
});

it('computes remaining recipients without going negative', function (): void {
    expect((new CampaignProgress(sent: 3, total: 10))->remaining())->toBe(7)
        ->and((new CampaignProgress(sent: 12, total: 10))->remaining())->toBe(0);
});

it('serialises to an array', function (): void {
    $progress = new CampaignProgress(
        status: CampaignStatus::Processing,
        sent: 5,
        pending: 5,
        total: 10,
    );

    expect($progress->toArray())->toBe([
        'status' => 'Processing',
        'sent' => 5,
        'failed' => 0,
        'pending' => 5,
        'total' => 10,
        'remaining' => 5,
        'percentage' => 50.0,
    ]);
});
