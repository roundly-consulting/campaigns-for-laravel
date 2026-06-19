<?php

declare(strict_types=1);

use RoundlyConsulting\Campaigns\Exceptions\CampaignException;
use RoundlyConsulting\Campaigns\Exceptions\CampaignNotFound;

it('builds a message containing the uuid', function (): void {
    $exception = CampaignNotFound::withUuid('abc-123');

    expect($exception->getMessage())->toContain('abc-123');
});

it('is catchable as the base campaign exception', function (): void {
    expect(CampaignNotFound::withUuid('x'))->toBeInstanceOf(CampaignException::class);
});
