<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionClass;
use RoundlyConsulting\Campaigns\CampaignsServiceProvider;
use RoundlyConsulting\Campaigns\Managers\InMemoryManager;
use RoundlyConsulting\Contacts\ContactsServiceProvider;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\OptionsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        InMemoryManager::flush();

        // The options package memoises resolved values in a static, per-process
        // cache that would otherwise leak across the fresh in-memory databases.
        Options::flushCache();
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            OptionsServiceProvider::class,
            ContactsServiceProvider::class,
            CampaignsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('mail.default', 'array');

        // Keep the options cache out of the way so each test reads fresh state.
        config()->set('options.cache.enabled', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadProviderSchema();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Schema::create('campaign_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Run the provider migrations the campaign integrations depend on. Their
     * migrations are publish-only too, so nothing is auto-discovered — the
     * suite loads each package's own directory explicitly.
     */
    private function loadProviderSchema(): void
    {
        $providers = [
            OptionsServiceProvider::class,
            ContactsServiceProvider::class,
        ];

        foreach ($providers as $provider) {
            $base = dirname((string) (new ReflectionClass($provider))->getFileName(), 2);

            $this->loadMigrationsFrom($base.'/database/migrations');
        }
    }
}
