<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Tests\Fixtures;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignManager;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Contracts\ProcessesCampaignRecipient;
use RuntimeException;

/**
 * A host delivery job that lets an exception escape for any recipient whose address starts
 * with `throw` — what a custom transport, a timeout or a job out of attempts looks like to the
 * batch: a failed job, no outcome recorded.
 */
final class ThrowingRecipientJob implements ProcessesCampaignRecipient, ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public Campaign $campaign, public CampaignRecipient $recipient) {}

    public function handle(CampaignManager $campaigns): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if (str_starts_with($this->recipient->reachableAt, 'throw')) {
            throw new RuntimeException('transport down');
        }

        $campaigns->campaign($this->campaign)->markProcessed($this->recipient);
    }
}
