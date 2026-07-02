<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Options;

use RoundlyConsulting\Options\BaseOption;

/**
 * Queue the campaign batch is dispatched on. Falls back to the
 * campaigns.batch-queue config value when unset.
 */
final class DefaultBatchQueue extends BaseOption
{
    public function castAs(): string
    {
        return 'string';
    }

    public function default(): string
    {
        return (string) config('campaigns.batch-queue', 'default');
    }
}
