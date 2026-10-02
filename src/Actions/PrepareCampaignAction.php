<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Actions;

use Illuminate\Support\Facades\Bus;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignProgress;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Contracts\CampaignStore;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Exceptions\CampaignAlreadyExists;
use RoundlyConsulting\Campaigns\Support\CampaignSettings;

/**
 * Store a campaign and its recipients, open its (empty) job batch and move it to Pending,
 * firing CampaignPrepared once the recipients are in place. Nothing is sent until the
 * campaign is started.
 *
 * The campaign's lifecycle fields start fresh (status Created, no timestamps, no batch).
 * Recipients are scoped to the campaign: one that belongs to another campaign is added as a
 * copy with a new uuid, never moved. A uuid another campaign already holds is refused —
 * preparing never overwrites (or re-sends) an existing campaign.
 */
final readonly class PrepareCampaignAction
{
    public function __construct(
        private CampaignStore $store,
        private ChangeCampaignStatusAction $changeStatus,
        private CampaignSettings $settings,
    ) {}

    /**
     * @param  iterable<CampaignRecipient>  $recipients
     *
     * @throws CampaignAlreadyExists when another campaign already holds the uuid
     */
    public function execute(Campaign $campaign, iterable $recipients = []): Campaign
    {
        $campaign = clone $campaign;
        $campaign->progress = new CampaignProgress;
        $campaign->startedAt = null;
        $campaign->endedAt = null;
        $campaign->batch = null;

        $recipients = CampaignRecipient::scopeAll($recipients, $campaign->uuid);

        if (! $this->store->insert($campaign)) {
            throw CampaignAlreadyExists::withUuid($campaign->uuid);
        }

        if ($recipients !== []) {
            $this->store->saveRecipients($campaign->uuid, $recipients);
        }

        // The callbacks are serialised into the batch and run in whichever process finishes
        // it, so they capture a plain snapshot and resolve the action there — never `$this`.
        $snapshot = clone $campaign;

        $batch = Bus::batch([])
            // A batch pushes every job it is given onto its own queue (a job's `$queue` is
            // overridden), so the batch is opened on the sending queue the deliveries run on.
            ->onQueue($this->settings->sendingQueue())
            ->finally(static fn () => app(ChangeCampaignStatusAction::class)->execute($snapshot, CampaignStatus::Completed))
            ->catch(static fn () => app(ChangeCampaignStatusAction::class)->execute($snapshot, CampaignStatus::Failed))
            ->allowFailures()
            ->dispatch();

        $campaign->batch = $batch->id;

        $this->store->save($campaign);

        return $this->changeStatus->execute($campaign, CampaignStatus::Pending);
    }
}
