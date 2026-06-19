<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Enums;

enum CampaignStatus: string
{
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

    /**
     * Human-readable, translatable label for display.
     */
    public function label(): string
    {
        return (string) trans('campaigns::campaigns.status.'.$this->value);
    }
}
