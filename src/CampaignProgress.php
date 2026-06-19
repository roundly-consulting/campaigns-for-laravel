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

    /**
     * Whether the campaign reached a terminal state.
     */
    public function isComplete(): bool
    {
        return $this->status->isTerminal();
    }

    /**
     * Whether the campaign is actively sending.
     */
    public function isRunning(): bool
    {
        return $this->status === CampaignStatus::Processing;
    }

    /**
     * Recipients left to process.
     */
    public function remaining(): int
    {
        return max($this->total - $this->sent, 0);
    }

    /**
     * @return array{status: string, sent: int, pending: int, total: int, remaining: int, percentage: float}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'sent' => $this->sent,
            'pending' => $this->pending,
            'total' => $this->total,
            'remaining' => $this->remaining(),
            'percentage' => $this->percentage(),
        ];
    }
}
