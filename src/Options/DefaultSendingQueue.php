<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Options;

use RoundlyConsulting\Options\BaseOption;

/**
 * Queue every per-recipient delivery job runs on: a campaign's job batch is opened on it when
 * the campaign is prepared. Falls back to the campaigns.sending-queue config value when unset.
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
