<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns;

use Closure;
use RoundlyConsulting\Campaigns\Commands\CampaignsCancelCommand;
use RoundlyConsulting\Campaigns\Commands\CampaignsListCommand;
use RoundlyConsulting\Campaigns\Contracts\CampaignStore;
use RoundlyConsulting\Campaigns\Options\DefaultChannel;
use RoundlyConsulting\Campaigns\Options\DefaultFromAddress;
use RoundlyConsulting\Campaigns\Options\DefaultFromName;
use RoundlyConsulting\Campaigns\Options\DefaultRecipientContactType;
use RoundlyConsulting\Campaigns\Options\DefaultSendingQueue;
use RoundlyConsulting\Campaigns\Options\OnlyVerifiedRecipients;
use RoundlyConsulting\Campaigns\Support\CampaignsConfig;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\PackageToolkit\Support\Config;

final class CampaignsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('campaigns')
            ->hasConfigFile()
            ->hasMigrations()
            ->hasCommands([
                CampaignsListCommand::class,
                CampaignsCancelCommand::class,
            ])
            ->contributesToAbout(static fn (): array => [
                'Store' => self::orInvalid(static fn (): string => class_basename(CampaignsConfig::store())),
                'Recipient job' => self::orInvalid(static fn (): string => class_basename(CampaignsConfig::recipientJob())),
                // The sender identity and the queue name are deployment details
                // (a sending domain, a host's queue topology), so the section
                // reports presence only — never the configured value.
                'From name' => self::presence('campaigns.from-name'),
                'From address' => self::presence('campaigns.from-address'),
                'Sending queue' => self::presence('campaigns.sending-queue', 'default'),
                'Notification' => self::presence('campaigns.notification'),
                'Notification channel' => self::orInvalid(CampaignsConfig::notificationChannel(...)),
                'Recipient contact type' => self::orInvalid(static fn (): string => CampaignsConfig::recipientContactType()->value),
                'Verified recipients only' => Config::boolean('campaigns.recipients.only-verified') ? 'ON' : 'OFF',
            ]);
    }

    public function register(): void
    {
        parent::register();

        // Scoped, not a singleton: Laravel drops it between queued jobs (and Octane between
        // requests), so the in-memory store holds one request's or job's campaigns and never
        // grows — or leaks campaigns into the next one — for a worker's lifetime.
        $this->app->scoped(CampaignStore::class, function (): CampaignStore {
            return resolve(CampaignsConfig::store());
        });

        $this->app->singleton(CampaignManager::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerOptions();
    }

    /**
     * Register the campaign send-default options so they are discoverable
     * through the options-for-laravel registry (list/get by key).
     */
    private function registerOptions(): void
    {
        Options::register([
            'campaigns.from-name' => DefaultFromName::class,
            'campaigns.from-address' => DefaultFromAddress::class,
            'campaigns.channel' => DefaultChannel::class,
            'campaigns.sending-queue' => DefaultSendingQueue::class,
            'campaigns.only-verified-recipients' => OnlyVerifiedRecipients::class,
            'campaigns.recipient-contact-type' => DefaultRecipientContactType::class,
        ]);
    }

    /**
     * A strict read rendered for `about`, or `INVALID` when the setting is broken — so
     * `php artisan about` still works on a misconfigured host while every real read throws.
     *
     * @param  Closure(): string  $read
     */
    private static function orInvalid(Closure $read): string
    {
        try {
            return $read();
        } catch (InvalidConfigurationException) {
            return 'INVALID';
        }
    }

    /**
     * Presence of a configured value — never the value itself. `DEFAULT` when
     * it is not set — absent or blank — (or still on the shipped fallback),
     * `NONE` when there is no fallback at all.
     */
    private static function presence(string $key, ?string $default = null): string
    {
        $value = config($key);
        $configured = is_string($value) && trim($value) !== '' && $value !== $default;

        return match (true) {
            $configured => 'SET',
            $default !== null => 'DEFAULT',
            default => 'NONE',
        };
    }
}
