<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\Managers\Manager;

final class CampaignsListCommand extends Command
{
    protected $signature = 'campaigns:list {--offset=0} {--limit=10}';

    protected $description = 'List campaigns and progress';

    public function handle(Manager $manager): int
    {
        /** @var list<array<int, string>> $campaigns */
        $campaigns = [];

        $manager->onEachCampaign(
            callback: function (Campaign $campaign) use (&$campaigns): void {
                $campaigns[] = [
                    $campaign->uuid,
                    $campaign->subject,
                    "{$campaign->fromName} ({$campaign->fromAddress})",
                    $campaign->progress->status->label(),
                    "{$campaign->progress->percentage()}% ({$campaign->progress->pending} to be sent of {$campaign->progress->total})",
                    $campaign->startedAt?->format('Y-m-d H:i') ?: 'N/A',
                    $campaign->endedAt?->format('Y-m-d H:i') ?: 'N/A',
                ];
            },
            offset: (int) $this->option('offset'),
            limit: (int) $this->option('limit'),
        );

        $this->table(
            headers: ['ID', 'Subject', 'Sender', 'Status', 'Progress', 'Started at', 'Ended at'],
            rows: $campaigns,
        );

        return self::SUCCESS;
    }
}
