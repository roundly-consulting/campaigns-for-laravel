<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Campaigns\CampaignManager;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Exceptions\CampaignNotFound;

final class CampaignsCancelCommand extends Command
{
    protected $signature = 'campaigns:cancel {uuid}';

    protected $description = 'Cancel a running campaign and its batch';

    public function handle(CampaignManager $campaigns): int
    {
        /** @var string $uuid */
        $uuid = $this->argument('uuid');

        try {
            $campaign = $campaigns->cancel($uuid);
        } catch (CampaignNotFound $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($campaign->progress->status !== CampaignStatus::Canceled) {
            $this->warn("Campaign [{$uuid}] already ended ({$campaign->progress->status->label()}); nothing to cancel.");

            return self::SUCCESS;
        }

        $this->info("Campaign [{$uuid}] cancelled.");

        return self::SUCCESS;
    }
}
