<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Options;

use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Options\BaseOption;
use RoundlyConsulting\Options\Casts\EnumCast;

/**
 * Contact kind resolved for a HasContacts owner passed to ->to() when
 * ->viaContactType() is unset. Falls back to the
 * campaigns.recipients.contact-type config value when unset.
 */
final class DefaultRecipientContactType extends BaseOption
{
    public function castAs(): string
    {
        return EnumCast::class.':'.ContactType::class;
    }

    public function default(): ContactType
    {
        $configured = config('campaigns.recipients.contact-type', 'email');

        return ContactType::tryFrom(is_string($configured) ? $configured : 'email')
            ?? ContactType::Email;
    }
}
