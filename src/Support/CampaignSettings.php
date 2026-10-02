<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Support;

use RoundlyConsulting\Campaigns\Options\DefaultChannel;
use RoundlyConsulting\Campaigns\Options\DefaultFromAddress;
use RoundlyConsulting\Campaigns\Options\DefaultFromName;
use RoundlyConsulting\Campaigns\Options\DefaultRecipientContactType;
use RoundlyConsulting\Campaigns\Options\DefaultSendingQueue;
use RoundlyConsulting\Campaigns\Options\OnlyVerifiedRecipients;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Options\Facades\Options;

/**
 * Typed reader over the campaigns send-defaults. Each setting resolves through
 * the options-for-laravel bag, which returns the stored option value when set
 * and the option's declared default() (seeded from config/campaigns.php)
 * otherwise — the option-over-config fallback chain.
 */
final class CampaignSettings
{
    public function fromName(): string
    {
        return (string) Options::get(DefaultFromName::class);
    }

    public function fromAddress(): string
    {
        return (string) Options::get(DefaultFromAddress::class);
    }

    public function notificationChannel(): string
    {
        return (string) Options::get(DefaultChannel::class);
    }

    public function sendingQueue(): string
    {
        return (string) Options::get(DefaultSendingQueue::class);
    }

    public function onlyVerifiedRecipients(): bool
    {
        return (bool) Options::get(OnlyVerifiedRecipients::class);
    }

    public function defaultRecipientContactType(): ContactType
    {
        $type = Options::get(DefaultRecipientContactType::class);

        return $type instanceof ContactType ? $type : ContactType::Email;
    }
}
