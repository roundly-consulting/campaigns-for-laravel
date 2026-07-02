<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Options;

use RoundlyConsulting\Options\BaseOption;

/**
 * Queue each per-recipient processing job is dispatched on. Falls back to the
 * campaigns.sending-queue config value when unset.
 */
final class DefaultSendingQueue extends BaseOption
{
    public function castAs(): string
    {
        return 'string';
    }

    public function default(): string
    {
        return (string) config('campaigns.sending-queue', 'default');
    }
}
