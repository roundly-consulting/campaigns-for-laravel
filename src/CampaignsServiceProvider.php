<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Campaigns\Commands\CampaignsCancelCommand;
use RoundlyConsulting\Campaigns\Commands\CampaignsListCommand;
use RoundlyConsulting\Campaigns\Managers\DatabaseManager;
use RoundlyConsulting\Campaigns\Managers\InMemoryManager;
use RoundlyConsulting\Campaigns\Managers\Manager;
use RoundlyConsulting\Campaigns\Options\DefaultBatchQueue;
use RoundlyConsulting\Campaigns\Options\DefaultChannel;
use RoundlyConsulting\Campaigns\Options\DefaultFromAddress;
use RoundlyConsulting\Campaigns\Options\DefaultFromName;
use RoundlyConsulting\Campaigns\Options\DefaultRecipientContactType;
use RoundlyConsulting\Campaigns\Options\DefaultSendingQueue;
use RoundlyConsulting\Campaigns\Options\OnlyVerifiedRecipients;
use RoundlyConsulting\Options\Facades\Options;

final class CampaignsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/campaigns.php', 'campaigns');

        $this->app->singleton(Manager::class, function (): Manager {
            /** @var class-string<Manager> $manager */
            $manager = config('campaigns.manager', InMemoryManager::class);

            return resolve($manager);
        });

        $this->app->singleton(CampaignManager::class, fn (Application $app): CampaignManager => new CampaignManager($app->make(Manager::class)));
    }

    public function boot(): void
    {
        $this->registerOptions();

        // The package only persists campaigns when the database manager is
        // selected, so migrations stay off for in-memory hosts.
        if ($this->usesDatabaseManager()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                CampaignsListCommand::class,
                CampaignsCancelCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/campaigns.php' => config_path('campaigns.php'),
            ], 'campaigns-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'campaigns-migrations');
        }
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

    private function usesDatabaseManager(): bool
    {
        return config('campaigns.manager', InMemoryManager::class) === DatabaseManager::class;
    }
}
