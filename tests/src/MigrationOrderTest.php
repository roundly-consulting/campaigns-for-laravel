<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RoundlyConsulting\Campaigns\CampaignsServiceProvider;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Models\CampaignRecipientRecord;
use RoundlyConsulting\Campaigns\Models\CampaignRecord;
use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\Testing\Database\DriverMatrix;

$migrations = __DIR__.'/../../database/migrations';

/**
 * P — the publish-only guards. The fleet publishes migrations timestamped rather than
 * auto-loading them; doing both runs both copies and dies on a duplicate table (bug #5, on
 * three packages). `2` pins the file count so neither check can pass over an empty or
 * relocated directory.
 */
it('never auto-loads its migrations — the host publishes them', function (): void {
    expect(CampaignsServiceProvider::class)->toNotAutoLoadMigrations();
});

it('publishes its migrations timestamp-injected into the host', function (): void {
    expect(CampaignsServiceProvider::class)->toPublishMigrationsTimestamped('campaigns-migrations', 2);
});

/**
 * R — the real-engine proof, `toApplyOnConnection` only.
 *
 * `toRejectBrokenOrderOnConnection` is deliberately NOT adopted, and this is the settled
 * rule rather than an omission: the negative control reverses the migration list, and with
 * no foreign-key edges Postgres has nothing to refuse — it would accept the reversed set and
 * the assertion would fail by design. That is the check working correctly against a shape it
 * does not fit.
 *
 * M is skipped for a re-verified reason rather than the spec's say-so: neither migration
 * declares a `constrained()`/`references()` edge, AND neither is a `Schema::table()` ALTER.
 * Both halves of `assertRunnable()` matter — the FK half and the ALTER-sorts-after-CREATE
 * half (approvals #2) — and campaigns has nothing for either. `campaign_recipients` points
 * at `campaigns` by convention only, with no FK declared.
 *
 * `migrations: 2` pins the file count, and the runner independently fails a set that
 * "applies cleanly" while creating no tables — an empty `up()` otherwise proves nothing.
 */
it('applies its migrations on postgres', function () use ($migrations): void {
    expect($migrations)->toApplyOnConnection('pgsql', migrations: 2);
})->skip(fn (): bool => ! test()->connectionAvailable('pgsql'), 'no postgres connection available');

/**
 * The driver-truth pin. It compares the env-DECLARED driver against what the connection
 * itself answers, so a "pgsql" leg that quietly stayed on SQLite — a decapitated
 * `defineEnvironment()`, a missing `TESTING_DB_DRIVER` — goes red here rather than passing
 * as a postgres run. It fires automatically, unlike reading a skip count by hand.
 */
it('runs on the driver the environment declares', function (): void {
    expect(DatabaseDriver::current())->toBe(DatabaseDriver::from(DriverMatrix::driver()));
});

/**
 * The DatabaseManager path is the half of campaigns that touches an engine at all (the
 * default InMemoryManager never does), so pinning a campaign + recipient round-trip on
 * whatever engine the leg configured proves those columns are usable rather than merely
 * creatable.
 */
it('round-trips a campaign and its recipients on the configured engine', function (): void {
    $campaignUuid = (string) Str::uuid();

    $campaign = CampaignRecord::create([
        'uuid' => $campaignUuid,
        'subject' => 'Launch',
        'content' => 'Hello',
        'from_name' => 'Acme',
        'from_address' => 'acme@example.test',
    ]);

    $recipient = CampaignRecipientRecord::create([
        'uuid' => (string) Str::uuid(),
        'campaign_uuid' => $campaignUuid,
        'name' => 'Someone',
        'reachable_at' => 'someone@example.test',
    ]);

    // The recipient link is a uuid string, not an FK — the one column type the drivers
    // render differently here (Postgres has a real uuid type; SQLite stores it as text).
    expect($recipient->fresh()->campaign_uuid)->toBe($campaignUuid)
        ->and($campaign->fresh()->uuid)->toBe($campaignUuid)
        ->and($campaign->fresh()->status)->toBe(CampaignStatus::Created)
        ->and($campaign->fresh()->sent)->toBe(0)
        ->and(DB::connection()->getDriverName())->toBe(DriverMatrix::driver());
});
