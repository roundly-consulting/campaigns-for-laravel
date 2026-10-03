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
use RoundlyConsulting\Campaigns\Support\CampaignBatches;
use RoundlyConsulting\Campaigns\Support\CampaignsConfig;

/**
 * Start sending a prepared campaign: move it to Processing (CampaignStarted) and queue one
 * `campaigns.process-recipient-job` per recipient into its batch. A campaign with no
 * recipients completes at once (CampaignCompleted).
 *
 * Only a Pending campaign starts. Starting one that is already sending (or finished) throws
 * instead of queueing every recipient a second time — also when two processes start it at
 * once: the status move is atomic, and only its winner queues the recipients.
 */
final readonly class StartCampaignAction
{
    public function __construct(
        private CampaignStore $store,
        private ChangeCampaignStatusAction $changeStatus,
        private FinishCampaignAction $finish,
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

        // Resolved before the status moves, so a misconfigured job class throws while the
        // campaign is still Pending instead of stranding it in Processing.
        $job = CampaignsConfig::recipientJob();

        // Processing first: on a sync queue the jobs run — and the batch finishes — inside add().
        // `from: Pending` makes the move a compare-and-set, so of two starts racing past the
        // check above only one gets here; the other throws instead of queueing every recipient
        // a second time.
        $campaign = $this->changeStatus->execute($campaign, CampaignStatus::Processing, from: CampaignStatus::Pending);

        $jobs = $this->store->recipients($uuid)
            ->map(static fn (CampaignRecipient $recipient): ProcessesCampaignRecipient => new $job($campaign, $recipient))
            ->all();

        // Nobody to send to (none added, or every owner filtered out): an empty batch never
        // finishes, so the campaign completes here instead of staying Processing forever.
        if ($jobs === []) {
            return $this->finish->execute($campaign);
        }

        $this->batches->find($campaign)?->add($jobs);

        return $this->batches->syncProgress($this->reread($campaign), $this->store);
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
