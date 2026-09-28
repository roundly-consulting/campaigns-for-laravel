<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Actions;

use Illuminate\Support\Carbon;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\Contracts\CampaignStore;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Events\CampaignCancelled;
use RoundlyConsulting\Campaigns\Events\CampaignCompleted;
use RoundlyConsulting\Campaigns\Events\CampaignFailed;
use RoundlyConsulting\Campaigns\Events\CampaignPrepared;
use RoundlyConsulting\Campaigns\Events\CampaignStarted;
use RoundlyConsulting\Campaigns\Support\CampaignBatches;

/**
 * @internal the one status transition every lifecycle action and the batch callbacks share
 *
 * A terminal status (Completed, Failed, Canceled) is final: no later transition leaves it,
 * so a cancelled campaign stays cancelled when its batch finishes. A transition to the
 * current status is a no-op (no event). The batch counters are copied onto the progress.
 *
 * When the store no longer holds the campaign — a deleted row, or the in-memory store in a
 * queue worker's process — the transition applies to the given snapshot: its event still
 * fires, but nothing is written.
 */
final readonly class ChangeCampaignStatusAction
{
    public function __construct(
        private CampaignStore $store,
        private CampaignBatches $batches,
    ) {}

    public function execute(Campaign $campaign, CampaignStatus $status): Campaign
    {
        $stored = $this->store->find($campaign->uuid);
        $target = $stored ?? $campaign;
        $current = $target->progress->status;

        if ($current === $status || $current->isTerminal()) {
            return $target;
        }

        $target->progress->status = $status;

        if ($status === CampaignStatus::Processing && $target->startedAt === null) {
            $target->startedAt = Carbon::now();
        }

        if ($status->isTerminal() && $target->endedAt === null) {
            $target->endedAt = Carbon::now();
        }

        $this->batches->syncProgress($target);

        if ($stored !== null) {
            $this->store->save($target);
        }

        $event = match ($status) {
            CampaignStatus::Pending => new CampaignPrepared($target),
            CampaignStatus::Processing => new CampaignStarted($target),
            CampaignStatus::Completed => new CampaignCompleted($target),
            CampaignStatus::Failed => new CampaignFailed($target),
            CampaignStatus::Canceled => new CampaignCancelled($target),
            CampaignStatus::Created => null,
        };

        if ($event !== null) {
            event($event);
        }

        return $target;
    }
}
