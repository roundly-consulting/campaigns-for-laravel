<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns;

use RoundlyConsulting\Campaigns\Enums\CampaignStatus;

final class CampaignProgress
{
    public function __construct(
        public CampaignStatus $status = CampaignStatus::Created,
        public int $sent = 0,
        public int $pending = 0,
        public int $total = 0,
    ) {}

    public function percentage(): float
    {
        if ($this->total > 0) {
            return round(
                ($this->sent / $this->total) * 100, 2
            );
        }

        return 0.0;
    }
}
