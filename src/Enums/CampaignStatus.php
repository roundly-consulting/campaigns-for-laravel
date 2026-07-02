<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Enums;

use RoundlyConsulting\Enums\Helpers;

enum CampaignStatus: string
{
    use Helpers;

    case Created = 'Created';
    case Pending = 'Pending';
    case Processing = 'Processing';
    case Completed = 'Completed';
    case Failed = 'Failed';
    case Canceled = 'Canceled';

    /**
     * Whether the status represents an end state no further work changes.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Failed,
            self::Canceled,
        ], strict: true);
    }
}
