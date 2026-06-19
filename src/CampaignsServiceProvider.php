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
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'campaigns');

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

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/campaigns'),
            ], 'campaigns-translations');
        }
    }

    private function usesDatabaseManager(): bool
    {
        return config('campaigns.manager', InMemoryManager::class) === DatabaseManager::class;
    }
}
