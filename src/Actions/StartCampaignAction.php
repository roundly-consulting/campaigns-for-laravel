<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Actions;

use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Contracts\CampaignStore;
use RoundlyConsulting\Campaigns\Contracts\ProcessesCampaignRecipient;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Exceptions\CampaignNotFound;
use RoundlyConsulting\Campaigns\Exceptions\InvalidCampaignTransition;
use RoundlyConsulting\Campaigns\Jobs\SendCampaignEmail;
use RoundlyConsulting\Campaigns\Support\CampaignBatches;

/**
 * Start sending a prepared campaign: move it to Processing (CampaignStarted) and queue one
 * `campaigns.process-recipient-job` per recipient into its batch.
 *
 * Only a Pending campaign starts. Starting one that is already sending (or finished) throws
 * instead of queueing every recipient a second time.
 */
final readonly class StartCampaignAction
{
    public function __construct(
        private CampaignStore $store,
        private ChangeCampaignStatusAction $changeStatus,
        private CampaignBatches $batches,
    ) {}

    /**
     * @throws CampaignNotFound
     * @throws InvalidCampaignTransition
     */
    public function execute(Campaign|string $campaign): Campaign
    {
        $uuid = $campaign instanceof Campaign ? $campaign->uuid : $campaign;
        $campaign = $this->store->find($uuid) ?? throw CampaignNotFound::withUuid($uuid);

        if ($campaign->progress->status !== CampaignStatus::Pending) {
            throw InvalidCampaignTransition::cannotStart($campaign);
        }

        // Processing first: on a sync queue the jobs run — and the batch finishes — inside add().
        $campaign = $this->changeStatus->execute($campaign, CampaignStatus::Processing);

        /** @var class-string<ProcessesCampaignRecipient> $job */
        $job = config('campaigns.process-recipient-job', SendCampaignEmail::class);

        $jobs = $this->store->recipients($uuid)
            ->map(static fn (CampaignRecipient $recipient): ProcessesCampaignRecipient => new $job($campaign, $recipient))
            ->all();

        $this->batches->find($campaign)?->add($jobs);

        return $this->batches->syncProgress($this->reread($campaign));
    }

    /**
     * On a sync queue the batch may already have finished inside add(), so the stored
     * campaign can be ahead of the one in hand.
     */
    private function reread(Campaign $campaign): Campaign
    {
        return $this->store->find($campaign->uuid) ?? $campaign;
    }
}
