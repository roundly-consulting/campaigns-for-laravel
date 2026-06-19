<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Campaigns\CampaignsServiceProvider;
use RoundlyConsulting\Campaigns\Managers\DatabaseManager;
use RoundlyConsulting\Campaigns\Managers\Manager;

uses()->beforeEach(function (): void {
    config()->set('campaigns.manager', DatabaseManager::class);
})->group('database-manager');

it('binds the database manager and loads its migrations when selected', function (): void {
    // Re-boot the provider with the database manager selected so its
    // migration-loading branch runs.
    (new CampaignsServiceProvider(app()))->boot();

    expect(resolve(Manager::class))->toBeInstanceOf(DatabaseManager::class)
        ->and(Schema::hasTable('campaigns'))->toBeTrue();
});
