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
use RoundlyConsulting\Campaigns\Exceptions\InvalidCampaignTransition;
use RoundlyConsulting\Campaigns\Support\CampaignBatches;

/**
 * @internal the one status transition every lifecycle action and the batch callbacks share
 *
 * A terminal status (Completed, Failed, Canceled) is final: no later transition leaves it,
 * so a cancelled campaign stays cancelled when its batch finishes. A transition to the
 * current status is a no-op (no event). The live counters are copied onto the progress.
 *
 * The write is a compare-and-set on the status that was read (CampaignStore::saveIfStatus()),
 * so two processes racing the same campaign can never both transition it: the loser fires no
 * event and gets the winner's campaign back (or InvalidCampaignTransition with `$from`).
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

    /**
     * @param  CampaignStatus|null  $from  the status the transition must start from; when given,
     *                                     a campaign in any other status — or one another process
     *                                     moved first — throws instead of becoming a no-op
     *
     * @throws InvalidCampaignTransition when `$from` is given and the campaign is not in it
     */
    public function execute(Campaign $campaign, CampaignStatus $status, ?CampaignStatus $from = null): Campaign
    {
        $stored = $this->store->find($campaign->uuid);
        $target = $stored ?? $campaign;
        $current = $target->progress->status;

        if ($from !== null && $current !== $from) {
            throw InvalidCampaignTransition::cannotMove($target, $status, $from);
        }

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

        $this->batches->syncProgress($target, $this->store);

        // Compare-and-set on the status read above: when another process moved the campaign
        // in between, its transition (and its event) stands and this one is dropped.
        if ($stored !== null && ! $this->store->saveIfStatus($target, $current)) {
            $winner = $this->store->find($campaign->uuid) ?? $target;

            if ($from !== null) {
                throw InvalidCampaignTransition::cannotMove($winner, $status, $from);
            }

            return $winner;
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
