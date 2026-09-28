<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns;

use Illuminate\Bus\Batch;
use Illuminate\Support\Collection;
use RoundlyConsulting\Campaigns\Contracts\CampaignStore;
use RoundlyConsulting\Campaigns\Exceptions\CampaignNotFound;
use RoundlyConsulting\Campaigns\Exceptions\InvalidCampaignTransition;
use RoundlyConsulting\Campaigns\Exceptions\RecipientNotFound;
use RoundlyConsulting\Campaigns\Support\CampaignBatches;

/**
 * `Campaigns::campaign($uuid)` — one campaign. Reads are fresh on every call; writes go
 * through the manager, so host overrides and `Campaigns::fake()` see them. The handle is
 * scoped: it never reads or writes a recipient of another campaign.
 */
final readonly class CampaignHandle
{
    /**
     * @internal build it with `Campaigns::campaign($campaign)`
     */
    public function __construct(
        private CampaignManager $campaigns,
        private CampaignStore $store,
        private CampaignBatches $batches,
        private Campaign $campaign,
    ) {}

    public function uuid(): string
    {
        return $this->campaign->uuid;
    }

    /**
     * The campaign as stored now, with live progress.
     *
     * @throws CampaignNotFound
     */
    public function get(): Campaign
    {
        return $this->campaigns->findOrFail($this->campaign->uuid);
    }

    /**
     * @throws CampaignNotFound
     */
    public function progress(): CampaignProgress
    {
        return $this->get()->progress;
    }

    /**
     * The campaign's recipients in the order they were added; a null limit returns them all.
     *
     * @return Collection<int, CampaignRecipient>
     */
    public function recipients(int $offset = 0, ?int $limit = null): Collection
    {
        return $this->store->recipients($this->campaign->uuid, $offset, $limit);
    }

    /**
     * @throws RecipientNotFound when the recipient is unknown or belongs to another campaign
     */
    public function recipient(CampaignRecipient|string $recipient): CampaignRecipient
    {
        $uuid = $recipient instanceof CampaignRecipient ? $recipient->uuid : $recipient;

        return $this->store->findRecipient($this->campaign->uuid, $uuid)
            ?? throw RecipientNotFound::inCampaign($this->campaign->uuid, $uuid);
    }

    /**
     * The campaign's job batch, or null before it is prepared (and under the fake).
     */
    public function batch(): ?Batch
    {
        return $this->batches->find($this->store->find($this->campaign->uuid) ?? $this->campaign);
    }

    /**
     * @throws CampaignNotFound
     * @throws InvalidCampaignTransition when the campaign is not Pending
     */
    public function start(): Campaign
    {
        return $this->campaigns->start($this->campaign->uuid);
    }

    /**
     * @throws CampaignNotFound
     */
    public function cancel(): Campaign
    {
        return $this->campaigns->cancel($this->campaign->uuid);
    }

    /**
     * Record a successful delivery — what a ProcessesCampaignRecipient job calls.
     *
     * @throws RecipientNotFound when the recipient belongs to another campaign
     */
    public function markProcessed(CampaignRecipient $recipient): CampaignRecipient
    {
        $this->guard($recipient);

        return $this->campaigns->markRecipientProcessed($this->campaign, $recipient);
    }

    /**
     * Record a failed delivery — what a ProcessesCampaignRecipient job calls.
     *
     * @throws RecipientNotFound when the recipient belongs to another campaign
     */
    public function markFailed(CampaignRecipient $recipient, string $error): CampaignRecipient
    {
        $this->guard($recipient);

        return $this->campaigns->markRecipientFailed($this->campaign, $recipient, $error);
    }

    private function guard(CampaignRecipient $recipient): void
    {
        if (! $recipient->belongsTo($this->campaign->uuid)) {
            throw RecipientNotFound::inCampaign($this->campaign->uuid, $recipient->uuid);
        }
    }
}
