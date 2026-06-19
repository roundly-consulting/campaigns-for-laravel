<?php

declare(strict_types=1);

use RoundlyConsulting\Campaigns\CampaignManager;
use RoundlyConsulting\Campaigns\Exceptions\CampaignNotFound;
use RoundlyConsulting\Campaigns\Managers\Manager;

it('exposes findOrFail through the orchestrator', function (): void {
    fakeBus();

    $campaigns = resolve(CampaignManager::class);

    $campaign = $campaigns->create('Subject', 'Body')->uuid('cm-1')->prepare();

    expect($campaigns->findOrFail('cm-1')->uuid)->toBe($campaign->uuid);
});

it('throws findOrFail for a missing campaign', function (): void {
    expect(fn () => resolve(CampaignManager::class)->findOrFail('missing'))
        ->toThrow(CampaignNotFound::class);
});

it('returns the underlying manager', function (): void {
    expect(resolve(CampaignManager::class)->manager())->toBeInstanceOf(Manager::class);
});
