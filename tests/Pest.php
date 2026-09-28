<?php

declare(strict_types=1);

use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
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
