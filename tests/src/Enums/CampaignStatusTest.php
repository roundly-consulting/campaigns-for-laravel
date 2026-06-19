<?php

declare(strict_types=1);

use RoundlyConsulting\Campaigns\Enums\CampaignStatus;

it('reports terminal statuses', function (): void {
    expect(CampaignStatus::Completed->isTerminal())->toBeTrue()
        ->and(CampaignStatus::Failed->isTerminal())->toBeTrue()
        ->and(CampaignStatus::Canceled->isTerminal())->toBeTrue();
});

it('reports non-terminal statuses', function (): void {
    expect(CampaignStatus::Created->isTerminal())->toBeFalse()
        ->and(CampaignStatus::Pending->isTerminal())->toBeFalse()
        ->and(CampaignStatus::Processing->isTerminal())->toBeFalse();
});

it('returns a translated label', function (): void {
    expect(CampaignStatus::Processing->label())->toBe('Processing');
});
