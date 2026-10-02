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

    /**
     * A clone owns its progress and timestamps, so a store that hands out copies never
     * shares mutable state with the caller.
     */
    public function __clone()
    {
        $this->progress = clone $this->progress;
        $this->startedAt = $this->startedAt?->copy();
        $this->endedAt = $this->endedAt?->copy();
    }

    /**
     * @return array{uuid: string, subject: string, content: string, fromName: string, fromAddress: string, progress: array{status: string, sent: int, failed: int, pending: int, total: int, remaining: int, percentage: float}, startedAt: string|null, endedAt: string|null, batch: string|null}
     */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'subject' => $this->subject,
            'content' => $this->content,
            'fromName' => $this->fromName,
            'fromAddress' => $this->fromAddress,
            'progress' => $this->progress->toArray(),
            'startedAt' => $this->startedAt?->toIso8601String(),
            'endedAt' => $this->endedAt?->toIso8601String(),
            'batch' => $this->batch,
        ];
    }
}
