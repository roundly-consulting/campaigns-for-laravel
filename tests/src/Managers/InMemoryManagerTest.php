<?php

declare(strict_types=1);

use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Managers\InMemoryManager;
use RoundlyConsulting\Campaigns\Managers\Manager;

beforeEach(function (): void {
    $this->manager = resolve(Manager::class);
});

it('returns null when finding a missing campaign', function (): void {
    expect($this->manager->find('missing-uuid'))->toBeNull();
});

it('returns null when finding a recipient for a missing campaign', function (): void {
    expect($this->manager->findRecipient('missing-uuid', 'missing-recipient'))->toBeNull();
});

it('returns null batch for a campaign without a batch id', function (): void {
    $campaign = new Campaign(
        uuid: 'no-batch',
        subject: 'No batch',
        content: 'Hello',
        fromName: 'Testing',
        fromAddress: 'test@testing.tld',
    );

    expect($this->manager->findBatchForCampaign($campaign))->toBeNull();
});

it('does nothing when starting a missing campaign', function (): void {
    $this->manager->start('missing-uuid');

    expect($this->manager->find('missing-uuid'))->toBeNull();
});

it('does nothing when cancelling a missing campaign', function (): void {
    $this->manager->cancel('missing-uuid');

    expect($this->manager->find('missing-uuid'))->toBeNull();
});

it('keeps progress untouched for a campaign without a batch', function (): void {
    $campaign = new Campaign(
        uuid: 'batchless',
        subject: 'Batchless',
        content: 'Hello',
        fromName: 'Testing',
        fromAddress: 'test@testing.tld',
    );

    $this->manager->addCampaign($campaign);
    $this->manager->cancel('batchless');

    expect($this->manager->find('batchless'))
        ->progress->status->toBe(CampaignStatus::Canceled)
        ->progress->total->toBe(0)
        ->progress->sent->toBe(0)
        ->progress->pending->toBe(0);
});

it('flushes stored campaigns and recipients', function (): void {
    $this->manager->addCampaign(new Campaign(
        uuid: 'flush-me',
        subject: 'Flush',
        content: 'Hello',
        fromName: 'Testing',
        fromAddress: 'test@testing.tld',
    ));

    InMemoryManager::flush();

    expect(InMemoryManager::$campaigns)->toBe([])
        ->and(InMemoryManager::$recipients)->toBe([]);
});
