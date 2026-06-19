<?php

declare(strict_types=1);

use RoundlyConsulting\Campaigns\CampaignProgress;

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
