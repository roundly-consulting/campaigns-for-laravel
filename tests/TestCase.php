<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Tests;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Campaigns\CampaignsServiceProvider;
use RoundlyConsulting\Contacts\ContactsServiceProvider;
use RoundlyConsulting\Options\Facades\Options;
use RoundlyConsulting\Options\OptionsServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The options package memoises resolved values in a static, per-process cache that
        // would otherwise leak across the fresh databases each test gets.
        Options::flushCache();
    }

    /**
     * Every provider campaigns hard-requires, in registration order. A host auto-discovers
     * these; the suite must list them or the test environment is a fiction.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function packageProviders(): array
    {
        return [
            OptionsServiceProvider::class,
            ContactsServiceProvider::class,
            CampaignsServiceProvider::class,
        ];
    }

    /**
     * No package auto-loads its migrations (they are publish-only), so the suite runs them
     * itself — exactly like a host app does after publishing. Every source is named by
     * **provider class**, never by a hand-resolved path: the base case reflects each
     * provider to its own `database/migrations`, so this keeps working when a provider
     * renames a file or composer moves the package between a symlinked path repo and a real
     * VCS install.
     *
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [
            OptionsServiceProvider::class,
            ContactsServiceProvider::class,
            CampaignsServiceProvider::class,
            __DIR__.'/database/migrations',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return [
            'mail.default' => 'array',
            // Keep the options cache out of the way so each test reads fresh state.
            'options.cache.enabled' => false,
        ];
    }
}
