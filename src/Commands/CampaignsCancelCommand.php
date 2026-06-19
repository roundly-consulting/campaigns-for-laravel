<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Campaigns\CampaignManager;
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
            $campaigns->cancel($uuid);
        } catch (CampaignNotFound $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Campaign [{$uuid}] cancelled.");

        return self::SUCCESS;
    }
}
