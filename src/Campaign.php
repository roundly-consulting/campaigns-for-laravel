<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns;

use Illuminate\Support\Carbon;

final class Campaign
{
    public function __construct(
        public string $uuid,
        public string $subject,
        public string $content,
        public string $fromName,
        public string $fromAddress,
        public CampaignProgress $progress = new CampaignProgress,
        public ?Carbon $startedAt = null,
        public ?Carbon $endedAt = null,
        public ?string $batch = null,
    ) {}
}
