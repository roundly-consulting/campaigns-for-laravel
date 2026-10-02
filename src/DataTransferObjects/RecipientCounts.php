<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\DataTransferObjects;

/**
 * A campaign's recipients by delivery outcome, as its store holds them: `processed` were
 * delivered, `failed` were recorded as failed, the rest have no outcome yet.
 */
final readonly class RecipientCounts
{
    public function __construct(
        public int $total = 0,
        public int $processed = 0,
        public int $failed = 0,
    ) {}
}
