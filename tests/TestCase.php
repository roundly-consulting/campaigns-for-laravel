<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use RoundlyConsulting\Campaigns\CampaignsServiceProvider;
use RoundlyConsulting\Campaigns\Managers\InMemoryManager;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            CampaignsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('mail.default', 'array');

        InMemoryManager::flush();
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
