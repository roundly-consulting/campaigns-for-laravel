<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns;

final class CampaignRecipient
{
    public function __construct(
        public string $uuid,
        public string $name,
        public string $reachableAt,
        public bool $hasBeenProcessed = false,
        public bool $errorOccured = false,
        public ?string $errorMessage = '',
    ) {}
}
