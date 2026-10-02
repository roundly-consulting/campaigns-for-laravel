<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use RoundlyConsulting\Campaigns\Actions\CancelCampaignAction;
use RoundlyConsulting\Campaigns\Actions\MarkRecipientFailedAction;
use RoundlyConsulting\Campaigns\Actions\MarkRecipientProcessedAction;
use RoundlyConsulting\Campaigns\Actions\PrepareCampaignAction;
use RoundlyConsulting\Campaigns\Actions\StartCampaignAction;
use RoundlyConsulting\Campaigns\Contracts\CampaignStore;
use RoundlyConsulting\Campaigns\Exceptions\CampaignAlreadyExists;
use RoundlyConsulting\Campaigns\Exceptions\CampaignNotFound;
use RoundlyConsulting\Campaigns\Exceptions\InvalidCampaignTransition;
use RoundlyConsulting\Campaigns\Exceptions\RecipientNotFound;
use RoundlyConsulting\Campaigns\Support\CampaignBatches;
use RoundlyConsulting\Campaigns\Support\CampaignSettings;

/**
 * The campaigns API — the root of the Campaigns facade, injectable by this class-string.
 * Every write resolves its action from the container; reads go to the configured
 * CampaignStore. Campaigns still sending report live progress from their job batch.
 */
class CampaignManager
{
    public function __construct(
        protected readonly Container $container,
    ) {}

    /**
     * Start a fluent campaign: `->from()->to()->prepare()` or `->dispatch()`.
     */
    public function create(string $subject, string $content): PendingCampaign
    {
        return new PendingCampaign($this, $subject, $content);
    }

    /**
     * Store a campaign with its recipients and leave it Pending (nothing is sent yet).
     *
     * @param  iterable<CampaignRecipient>  $recipients
     *
     * @throws CampaignAlreadyExists when another campaign already holds the uuid
     */
    public function prepare(Campaign $campaign, iterable $recipients = []): Campaign
    {
        return $this->container->make(PrepareCampaignAction::class)->execute($campaign, $recipients);
    }

    /**
     * Start sending a prepared (Pending) campaign.
     *
     * @throws CampaignNotFound
     * @throws InvalidCampaignTransition when the campaign is not Pending
     */
    public function start(Campaign|string $campaign): Campaign
    {
        return $this->container->make(StartCampaignAction::class)->execute($campaign);
    }

    /**
     * Cancel a campaign and its batch; a campaign that already ended is returned unchanged.
     *
     * @throws CampaignNotFound
     */
    public function cancel(Campaign|string $campaign): Campaign
    {
        return $this->container->make(CancelCampaignAction::class)->execute($campaign);
    }

    public function find(string $uuid): ?Campaign
    {
        $campaign = $this->store()->find($uuid);

        return $campaign === null ? null : $this->live($campaign);
    }

    /**
     * @throws CampaignNotFound
     */
    public function findOrFail(string $uuid): Campaign
    {
        return $this->find($uuid) ?? throw CampaignNotFound::withUuid($uuid);
    }

    /**
     * A page of campaigns in creation order.
     *
     * @return Collection<int, Campaign>
     */
    public function all(int $offset = 0, int $limit = 10): Collection
    {
        return $this->store()->all($offset, $limit)
            ->map(fn (Campaign $campaign): Campaign => $this->live($campaign))
            ->values();
    }

    /**
     * One campaign: progress, recipients, batch, start, cancel and delivery outcomes.
     *
     * @throws CampaignNotFound when given a uuid the store does not hold
     */
    public function campaign(Campaign|string $campaign): CampaignHandle
    {
        return new CampaignHandle(
            $this,
            $this->store(),
            $this->container->make(CampaignBatches::class),
            $campaign instanceof Campaign ? $campaign : $this->findOrFail($campaign),
        );
    }

    /**
     * The effective send defaults (stored option, else config).
     */
    public function settings(): CampaignSettings
    {
        return $this->container->make(CampaignSettings::class);
    }

    /**
     * @internal use `Campaigns::campaign($campaign)->markProcessed($recipient)`
     *
     * @throws RecipientNotFound
     */
    public function markRecipientProcessed(Campaign $campaign, CampaignRecipient $recipient): CampaignRecipient
    {
        return $this->container->make(MarkRecipientProcessedAction::class)->execute($campaign, $recipient);
    }

    /**
     * @internal use `Campaigns::campaign($campaign)->markFailed($recipient, $error)`
     *
     * @throws RecipientNotFound
     */
    public function markRecipientFailed(Campaign $campaign, CampaignRecipient $recipient, string $error): CampaignRecipient
    {
        return $this->container->make(MarkRecipientFailedAction::class)->execute($campaign, $recipient, $error);
    }

    protected function store(): CampaignStore
    {
        return $this->container->make(CampaignStore::class);
    }

    /**
     * Counters are persisted at each status change; while a campaign is Pending or
     * Processing they are read live from its recipients and its batch.
     */
    private function live(Campaign $campaign): Campaign
    {
        if ($campaign->progress->status->isTerminal()) {
            return $campaign;
        }

        return $this->container->make(CampaignBatches::class)->syncProgress($campaign, $this->store());
    }
}
