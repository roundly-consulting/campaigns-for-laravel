<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Exceptions\CampaignAlreadyExists;
use RoundlyConsulting\Campaigns\Facades\Campaigns;
use RoundlyConsulting\Campaigns\Stores\DatabaseCampaignStore;

/**
 * Preparing is all-or-nothing against the real batch repository: the batch is opened before
 * the campaign is stored, so a batch that cannot be opened stores nothing (the uuid stays free
 * for a retry), and a refused uuid leaves no orphan batch behind.
 */
it('stores nothing when the batch cannot be opened, so the same uuid can be retried', function (string $store): void {
    useStore($store);
    useDatabaseQueue();

    Schema::rename('job_batches', 'job_batches_missing');

    expect(fn () => Campaigns::create('Subject', 'Body')->uuid('00000000-0000-4000-8000-c00000000001')->to('a@a.tld')->prepare())
        ->toThrow(QueryException::class)
        ->and(Campaigns::find('00000000-0000-4000-8000-c00000000001'))->toBeNull();

    Schema::rename('job_batches_missing', 'job_batches');

    expect(Campaigns::create('Subject', 'Body')->uuid('00000000-0000-4000-8000-c00000000001')->to('a@a.tld')->prepare())
        ->progress->status->toBe(CampaignStatus::Pending);
})->with('stores');

it('leaves no batch behind for a refused uuid', function (): void {
    useStore(DatabaseCampaignStore::class);
    useDatabaseQueue();

    $first = Campaigns::create('First', 'Body')->to('a@a.tld')->prepare();

    expect(fn () => Campaigns::create('Other', 'Body')->uuid($first->uuid)->prepare())->toThrow(CampaignAlreadyExists::class)
        ->and(DB::table('job_batches')->pluck('id')->all())->toBe([$first->batch]);
});
