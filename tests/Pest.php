<?php

declare(strict_types=1);

use Illuminate\Bus\BatchRepository;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Testing\Fakes\BatchRepositoryFake;
use RoundlyConsulting\Campaigns\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function fakeBus(): void
{
    $busFakeRepository = new BatchRepositoryFake;
    app()->singleton(BatchRepository::class, fn () => $busFakeRepository);
    Bus::fake(batchRepository: $busFakeRepository);
}
