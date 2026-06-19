<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns;

use Closure;
use RoundlyConsulting\Campaigns\Exceptions\CampaignNotFound;
use RoundlyConsulting\Campaigns\Managers\Manager;

/**
 * Thin, discoverable orchestrator over the configured Manager. Backs the
 * Campaigns facade and produces fluent PendingCampaign builders.
 */
final class CampaignManager
{
    public function __construct(
        private readonly Manager $manager,
    ) {}

    public function create(string $subject, string $content): PendingCampaign
    {
        return new PendingCampaign($this->manager, $subject, $content);
    }

    public function find(string $uuid): ?Campaign
    {
        return $this->manager->find($uuid);
    }

    /**
     * @throws CampaignNotFound
     */
    public function findOrFail(string $uuid): Campaign
    {
        return $this->manager->findOrFail($uuid);
    }

    /**
     * @throws CampaignNotFound
     */
    public function cancel(string $uuid): void
    {
        $this->manager->cancel($uuid);
    }

    public function each(Closure $callback, int $offset = 0, int $limit = 10): void
    {
        $this->manager->onEachCampaign($callback, $offset, $limit);
    }

    public function manager(): Manager
    {
        return $this->manager;
    }
}
