<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns;

use RoundlyConsulting\Campaigns\Commands\CampaignsCancelCommand;
use RoundlyConsulting\Campaigns\Commands\CampaignsListCommand;
use RoundlyConsulting\Campaigns\Contracts\CampaignStore;
use RoundlyConsulting\Campaigns\Jobs\SendCampaignEmail;
use RoundlyConsulting\Campaigns\Options\DefaultBatchQueue;
use RoundlyConsulting\Campaigns\Options\DefaultChannel;
use RoundlyConsulting\Campaigns\Options\DefaultFromAddress;
use RoundlyConsulting\Campaigns\Options\DefaultFromName;
use RoundlyConsulting\Campaigns\Options\DefaultRecipientContactType;
use RoundlyConsulting\Campaigns\Options\DefaultSendingQueue;
use RoundlyConsulting\Campaigns\Options\OnlyVerifiedRecipients;
use RoundlyConsulting\Campaigns\Stores\InMemoryCampaignStore;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;

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
                'Store' => class_basename(self::configuredString('campaigns.store', InMemoryCampaignStore::class)),
                'Recipient job' => class_basename(self::configuredString('campaigns.process-recipient-job', SendCampaignEmail::class)),
                // The sender identity and the queue names are deployment details
                // (a sending domain, a host's queue topology), so the section
                // reports presence only — never the configured value.
                'From name' => self::presence('campaigns.from-name'),
                'From address' => self::presence('campaigns.from-address'),
                'Batch queue' => self::presence('campaigns.batch-queue', 'default'),
                'Sending queue' => self::presence('campaigns.sending-queue', 'default'),
                'Notification' => self::presence('campaigns.notification'),
                'Notification channel' => self::configuredString('campaigns.notification-channel', 'mail'),
                'Recipient contact type' => self::configuredString('campaigns.recipients.contact-type', 'email'),
                'Verified recipients only' => (bool) config('campaigns.recipients.only-verified', false) ? 'ON' : 'OFF',
            ]);
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(CampaignStore::class, function (): CampaignStore {
            /** @var class-string<CampaignStore> $store */
            $store = self::configuredString('campaigns.store', InMemoryCampaignStore::class);

            return resolve($store);
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
            'campaigns.batch-queue' => DefaultBatchQueue::class,
            'campaigns.sending-queue' => DefaultSendingQueue::class,
            'campaigns.only-verified-recipients' => OnlyVerifiedRecipients::class,
            'campaigns.recipient-contact-type' => DefaultRecipientContactType::class,
        ]);
    }

    private static function configuredString(string $key, string $default): string
    {
        $value = config($key, $default);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * Presence of a configured value — never the value itself. `DEFAULT` when
     * it is absent (or still on the shipped fallback), `NONE` when there is no
     * fallback at all.
     */
    private static function presence(string $key, ?string $default = null): string
    {
        $value = config($key);
        $configured = is_string($value) && $value !== '' && $value !== $default;

        return match (true) {
            $configured => 'SET',
            $default !== null => 'DEFAULT',
            default => 'NONE',
        };
    }
}
