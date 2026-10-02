<?php

declare(strict_types=1);

use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\DatabaseBatchRepository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Testing\Fakes\BatchRepositoryFake;
use RoundlyConsulting\Campaigns\Contracts\CampaignStore;
use RoundlyConsulting\Campaigns\Stores\DatabaseCampaignStore;
use RoundlyConsulting\Campaigns\Stores\InMemoryCampaignStore;
use RoundlyConsulting\Campaigns\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Every lifecycle test that takes this dataset runs once per shipped store, which is what
 * proves the two behave the same.
 */
dataset('stores', [
    'in-memory' => InMemoryCampaignStore::class,
    'database' => DatabaseCampaignStore::class,
]);

function fakeBus(): void
{
    $busFakeRepository = new BatchRepositoryFake;
    app()->singleton(BatchRepository::class, fn () => $busFakeRepository);
    Bus::fake(batchRepository: $busFakeRepository);
}

/**
 * @param  class-string<CampaignStore>  $store
 */
function useStore(string $store): void
{
    config()->set('campaigns.store', $store);
    app()->forgetInstance(CampaignStore::class);
}

/**
 * Run the batch's `finally` (or `catch`) callbacks, as the queue does when it finishes.
 *
 * @param  'finally'|'catch'  $which
 */
function finishBatch(Batch $batch, string $which = 'finally'): void
{
    foreach ($batch->options[$which] ?? [] as $callback) {
        $callback($batch, new RuntimeException('boom'));
    }
}

/**
 * Run campaigns through a real database queue and the database batch repository, as a host
 * with `QUEUE_CONNECTION=database` does — the shape the bus fake cannot show (which queue a
 * job lands on, a job that throws, a batch that finishes in the worker).
 */
function useDatabaseQueue(): void
{
    $connection = config('database.default');

    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.connection', $connection);
    config()->set('queue.batching.database', $connection);
    config()->set('queue.failed.driver', 'null');

    app()->forgetInstance(DatabaseBatchRepository::class);
    app()->forgetInstance(BatchRepository::class);
}

/**
 * Work the database queue in this process: `$jobs` jobs, or until it is empty.
 */
function workQueue(string $queue = 'default', ?int $jobs = null): void
{
    Artisan::call('queue:work', array_filter([
        'connection' => 'database',
        '--queue' => $queue,
        '--stop-when-empty' => true,
        '--max-jobs' => $jobs,
        '--sleep' => 0,
    ], static fn (mixed $value): bool => $value !== null));
}
