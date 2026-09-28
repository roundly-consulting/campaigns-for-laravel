<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Message;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignManager;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Contracts\ProcessesCampaignRecipient;
use RoundlyConsulting\Campaigns\Support\CampaignSettings;
use Throwable;

final class SendCampaignEmail implements ProcessesCampaignRecipient, ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Campaign $campaign, public CampaignRecipient $recipient)
    {
        $this->queue = app(CampaignSettings::class)->sendingQueue();
    }

    public function handle(CampaignManager $campaigns): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        try {
            Mail::html(
                $this->campaign->content,
                fn (Message $message) => $message->subject($this->campaign->subject)
                    ->from(
                        address: $this->campaign->fromAddress,
                        name: $this->campaign->fromName,
                    )
                    ->to(
                        address: $this->recipient->reachableAt,
                        name: $this->recipient->name,
                    )
            );

            $campaigns->campaign($this->campaign)->markProcessed($this->recipient);
        } catch (Throwable $e) {
            $campaigns->campaign($this->campaign)->markFailed($this->recipient, $e->getMessage());
        }
    }
}
