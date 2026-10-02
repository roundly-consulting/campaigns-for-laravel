<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Support;

use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Contracts\Container\Container;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\Contracts\CampaignStore;

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
     * Fill the campaign's live counters in (in place, not saved). Deliveries come from the
     * recipients' recorded outcomes, so a failure is never counted as sent; a job that failed
     * outright (it threw, timed out, ran out of attempts) recorded nothing, so the batch's
     * failed-job count is added to `failed`.
     *
     * A store that does not hold the recipients — the in-memory store in another process —
     * leaves the batch as the only source: there a job that recorded a failure cannot be told
     * from a delivery.
     */
    public function syncProgress(Campaign $campaign, CampaignStore $store): Campaign
    {
        $batch = $this->find($campaign);
        $counts = $store->countRecipients($campaign->uuid);
        $progress = $campaign->progress;

        if ($counts->total === 0 && $batch instanceof Batch && $batch->totalJobs > 0) {
            $progress->total = $batch->totalJobs;
            $progress->sent = $batch->processedJobs();
            $progress->failed = $batch->failedJobs;
        } else {
            $progress->total = $counts->total;
            $progress->sent = $counts->processed;
            // A retried job can leave the batch's failed count behind; never exceed what is left.
            $progress->failed = min($counts->failed + ($batch->failedJobs ?? 0), $counts->total - $counts->processed);
        }

        $progress->pending = $progress->remaining();

        return $campaign;
    }
}
