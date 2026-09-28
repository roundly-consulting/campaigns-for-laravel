<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Campaigns\CampaignManager;
use RoundlyConsulting\Campaigns\CampaignsServiceProvider;
use RoundlyConsulting\Campaigns\Commands\CampaignsCancelCommand;
use RoundlyConsulting\Campaigns\Commands\CampaignsListCommand;
use RoundlyConsulting\Campaigns\Contracts\CampaignStore;
use RoundlyConsulting\Campaigns\Stores\DatabaseCampaignStore;
use RoundlyConsulting\Campaigns\Stores\InMemoryCampaignStore;

it('merges the package config', function (): void {
    expect(config('campaigns.store'))->toBe(InMemoryCampaignStore::class)
        ->and(config('campaigns.process-recipient-job'))->toBeString()
        ->and(config('campaigns.recipients.contact-type'))->toBe('email');
});

it('binds the in-memory store by default', function (): void {
    expect(resolve(CampaignStore::class))->toBeInstanceOf(InMemoryCampaignStore::class)
        ->and(resolve(CampaignStore::class))->toBe(resolve(CampaignStore::class))
        ->and(resolve(CampaignManager::class))->toBeInstanceOf(CampaignManager::class)
        ->and(resolve(CampaignManager::class))->toBe(resolve(CampaignManager::class));
});

it('binds the database store when it is configured', function (): void {
    config()->set('campaigns.store', DatabaseCampaignStore::class);

    expect(resolve(CampaignStore::class))->toBeInstanceOf(DatabaseCampaignStore::class)
        ->and(Schema::hasTable('campaigns'))->toBeTrue()
        ->and(Schema::hasTable('campaign_recipients'))->toBeTrue();
});

it('falls back to the in-memory store when the configured value is unusable', function (): void {
    config()->set('campaigns.store', '');

    expect(resolve(CampaignStore::class))->toBeInstanceOf(InMemoryCampaignStore::class);
});

it('registers every publish tag', function (string $tag): void {
    expect(ServiceProvider::pathsToPublish(CampaignsServiceProvider::class, $tag))->not->toBeEmpty();
})->with([
    'campaigns-config',
    'campaigns-migrations',
]);

it('publishes the config file', function (): void {
    $paths = ServiceProvider::pathsToPublish(CampaignsServiceProvider::class, 'campaigns-config');

    expect($paths)->toBe([
        realpath(__DIR__.'/../../config/campaigns.php') => config_path('campaigns.php'),
    ]);
});

it('publishes both migrations timestamp-injected into the host, in order', function (): void {
    $paths = ServiceProvider::pathsToPublish(CampaignsServiceProvider::class, 'campaigns-migrations');

    expect($paths)->toHaveCount(2);

    $sources = array_map(basename(...), array_keys($paths));
    $targets = array_values($paths);

    expect($sources)->toBe([
        '2026_06_20_000001_create_campaigns_table.php',
        '2026_06_20_000002_create_campaign_recipients_table.php',
    ]);

    foreach ($targets as $target) {
        expect(dirname((string) $target))->toBe(database_path('migrations'));
    }

    expect(basename((string) $targets[0]))->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_create_campaigns_table\.php$/')
        ->and(basename((string) $targets[1]))->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_create_campaign_recipients_table\.php$/');

    // The recipients table must publish after the campaigns table so the host
    // runs them in the directory's order.
    expect(basename((string) $targets[1]))->toBeGreaterThan(basename((string) $targets[0]));
});

it('never auto-loads its migrations — the host must publish them', function (): void {
    $registered = array_map(
        static fn (string $path): string => realpath($path) ?: $path,
        app('migrator')->paths(),
    );

    expect($registered)->not->toContain(realpath(__DIR__.'/../../database/migrations'));
});

it('registers the package commands', function (string $signature, string $class): void {
    expect(Artisan::all())->toHaveKey($signature)
        ->and(Artisan::all()[$signature])->toBeInstanceOf($class);
})->with([
    ['campaigns:list', CampaignsListCommand::class],
    ['campaigns:cancel', CampaignsCancelCommand::class],
]);

it('contributes a campaigns section to about', function (string $expected): void {
    $this->artisan('about --only=campaigns')
        ->expectsOutputToContain($expected)
        ->assertExitCode(0);
})->with([
    'Campaigns',
    'Store',
    'Recipient job',
    'From name',
    'From address',
    'Batch queue',
    'Sending queue',
    'Notification',
    'Notification channel',
    'Recipient contact type',
    'Verified recipients only',
]);

it('reports the configured store and recipient job by base name in about', function (): void {
    config()->set('campaigns.store', DatabaseCampaignStore::class);

    $this->artisan('about --only=campaigns')
        ->expectsOutputToContain('DatabaseCampaignStore')
        ->assertExitCode(0);
});

it('reports the sender identity and queues by presence, never their values', function (): void {
    config()->set('campaigns.from-name', 'Acme Billing');
    config()->set('campaigns.from-address', 'billing@acme.test');
    config()->set('campaigns.batch-queue', 'acme-batches');
    config()->set('campaigns.sending-queue', 'acme-sending');
    config()->set('campaigns.notification', 'App\\Notifications\\AcmeBlast');

    $this->artisan('about --only=campaigns')
        ->doesntExpectOutputToContain('Acme Billing')
        ->doesntExpectOutputToContain('billing@acme.test')
        ->doesntExpectOutputToContain('acme-batches')
        ->doesntExpectOutputToContain('acme-sending')
        ->doesntExpectOutputToContain('AcmeBlast')
        ->expectsOutputToContain('SET')
        ->assertExitCode(0);
});

it('reports an unset sender and notification as DEFAULT or NONE', function (): void {
    $this->artisan('about --only=campaigns')
        ->expectsOutputToContain('NONE')
        ->expectsOutputToContain('DEFAULT')
        ->assertExitCode(0);
});

it('reports verified-only recipients as on when configured', function (): void {
    config()->set('campaigns.recipients.only-verified', true);

    $this->artisan('about --only=campaigns')
        ->expectsOutputToContain('ON')
        ->assertExitCode(0);
});
