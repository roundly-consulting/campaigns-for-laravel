<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Support;

use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Container\Container;
use RoundlyConsulting\Campaigns\Campaign;

/**
 * @internal the campaign ↔ job-batch link the actions, the manager and the handle share
 */
final readonly class CampaignBatches
{
    public function __construct(
        private Container $container,
    ) {}

    public function find(Campaign $campaign): ?Batch
    {
        if ($campaign->batch === null) {
            return null;
        }

        /** @var BatchRepository $batches */
        $batches = $this->container->make(BatchRepository::class);

        return $batches->find($campaign->batch);
    }

    /**
     * Copy the batch's live counters onto the campaign's progress (in place, not saved).
     */
    public function syncProgress(Campaign $campaign): Campaign
    {
        $batch = $this->find($campaign);

        if ($batch instanceof Batch) {
            $campaign->progress->total = $batch->totalJobs;
            $campaign->progress->sent = $batch->processedJobs();
            $campaign->progress->pending = $batch->pendingJobs;
        }

        return $campaign;
    }
}
