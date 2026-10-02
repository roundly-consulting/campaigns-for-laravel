<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns;

use RoundlyConsulting\Campaigns\Enums\CampaignStatus;

/**
 * Where a campaign stands: `total` recipients, of which `sent` were delivered, `failed` failed
 * (recorded as failed by their job, or their job failed outright) and `pending` have no outcome
 * yet. A failed delivery is never counted as sent.
 */
final class CampaignProgress
{
    public function __construct(
        public CampaignStatus $status = CampaignStatus::Created,
        public int $sent = 0,
        public int $pending = 0,
        public int $total = 0,
        public int $failed = 0,
    ) {}

    /**
     * The share of recipients delivered so far (failures excluded), 0–100.
     */
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
     * Recipients with no outcome yet (neither sent nor failed).
     */
    public function remaining(): int
    {
        return max($this->total - $this->sent - $this->failed, 0);
    }

    /**
     * @return array{status: string, sent: int, failed: int, pending: int, total: int, remaining: int, percentage: float}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'sent' => $this->sent,
            'failed' => $this->failed,
            'pending' => $this->pending,
            'total' => $this->total,
            'remaining' => $this->remaining(),
            'percentage' => $this->percentage(),
        ];
    }
}
