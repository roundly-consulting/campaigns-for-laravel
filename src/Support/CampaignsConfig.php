<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Support;

use RoundlyConsulting\Campaigns\Contracts\CampaignStore;
use RoundlyConsulting\Campaigns\Contracts\ProcessesCampaignRecipient;
use RoundlyConsulting\Campaigns\Jobs\SendCampaignEmail;
use RoundlyConsulting\Campaigns\Notifications\CampaignNotification;
use RoundlyConsulting\Campaigns\Stores\InMemoryCampaignStore;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * Strict readers for the package's non-boolean settings.
 *
 * A setting that is not set — absent, null, or blank like a host's `KEY=` — takes its default
 * (for the optional notification, none; for a sender field, `''`: the mailer's own). Anything
 * else unusable — an `emial` contact type, a non-string queue, a store or job class that does not
 * implement its contract — throws {@see InvalidConfigurationException} naming the key, instead
 * of quietly falling back (an unknown contact type used to read as email, a junk store as the
 * in-memory one).
 *
 * @internal
 */
final class CampaignsConfig
{
    /**
     * @return class-string<CampaignStore>
     */
    public static function store(): string
    {
        return self::implementation('campaigns.store', CampaignStore::class, InMemoryCampaignStore::class);
    }

    /**
     * @return class-string<ProcessesCampaignRecipient>
     */
    public static function recipientJob(): string
    {
        return self::implementation('campaigns.process-recipient-job', ProcessesCampaignRecipient::class, SendCampaignEmail::class);
    }

    /**
     * The notification class `SendCampaignNotification` delivers, or null when none is set.
     *
     * @return class-string<CampaignNotification>|null
     */
    public static function notification(): ?string
    {
        return self::unlessBlank(config('campaigns.notification')) === null
            ? null
            : self::implementation('campaigns.notification', CampaignNotification::class, CampaignNotification::class);
    }

    public static function notificationChannel(): string
    {
        return self::string('campaigns.notification-channel', 'mail');
    }

    public static function sendingQueue(): string
    {
        return self::string('campaigns.sending-queue', 'default');
    }

    /** The default sender name; `''` (documented: keep the mailer's "from") when not set (absent or blank). */
    public static function fromName(): string
    {
        return self::sender('campaigns.from-name');
    }

    /** The default sender address; `''` (documented: keep the mailer's "from") when not set (absent or blank). */
    public static function fromAddress(): string
    {
        return self::sender('campaigns.from-address');
    }

    public static function recipientContactType(): ContactType
    {
        return Config::enum('campaigns.recipients.contact-type', ContactType::class, ContactType::Email);
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $contract
     * @param  class-string<T>  $default
     * @return class-string<T>
     */
    private static function implementation(string $key, string $contract, string $default): string
    {
        $class = self::unlessBlank(config($key)) ?? $default;

        if (! is_string($class) || ! is_subclass_of($class, $contract)) {
            throw InvalidConfigurationException::notAnImplementation($key, $contract, $class);
        }

        return $class;
    }

    private static function string(string $key, string $default): string
    {
        $value = self::unlessBlank(config($key));

        if ($value === null) {
            return $default;
        }

        if (! is_string($value)) {
            throw InvalidConfigurationException::notAString($key, $value);
        }

        return $value;
    }

    /** A sender field: any string; not set (absent or blank) is `''`, meaning "the mailer's own". */
    private static function sender(string $key): string
    {
        $value = self::unlessBlank(config($key)) ?? '';

        if (! is_string($value)) {
            throw new InvalidConfigurationException(
                "Configuration value [{$key}] must be a string ('' keeps the mailer's from), [".get_debug_type($value).'] given.',
            );
        }

        return $value;
    }

    /**
     * A raw config value, with a blank string (`''` or whitespace — a host's `KEY=`) read as
     * null: not set, exactly like an absent key.
     */
    private static function unlessBlank(mixed $value): mixed
    {
        return is_string($value) && trim($value) === '' ? null : $value;
    }
}
