<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Campaigns\Commands\CampaignsListCommand;
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
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CampaignsListCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/campaigns.php' => config_path('campaigns.php'),
            ], 'campaigns-config');
        }
    }
}
