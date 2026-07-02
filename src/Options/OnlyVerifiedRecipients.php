<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Options;

use RoundlyConsulting\Options\BaseOption;

/**
 * Whether contact-resolved recipients must be verified. When true, owners and
 * contacts without a verified contact of the send kind are skipped. Falls back
 * to the campaigns.recipients.only-verified config value when unset.
 */
final class OnlyVerifiedRecipients extends BaseOption
{
    public function castAs(): string
    {
        return 'boolean';
    }

    public function default(): bool
    {
        return (bool) config('campaigns.recipients.only-verified', false);
    }
}
