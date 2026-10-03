<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Options;

use RoundlyConsulting\Campaigns\Support\CampaignsConfig;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Options\BaseOption;
use RoundlyConsulting\Options\Casts\EnumCast;

/**
 * Contact kind resolved for a HasContacts owner passed to ->to() when
 * ->viaContactType() is unset. Defaults to the
 * campaigns.recipients.contact-type config value when unset; a value that is not
 * a ContactType throws.
 */
final class DefaultRecipientContactType extends BaseOption
{
    public function castAs(): string
    {
        return EnumCast::class.':'.ContactType::class;
    }

    public function default(): ContactType
    {
        return CampaignsConfig::recipientContactType();
    }
}
