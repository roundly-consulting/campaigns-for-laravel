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
use RoundlyConsulting\Campaigns\Exceptions\RecipientHasNoAddress;
use RoundlyConsulting\Campaigns\Support\CampaignSettings;
use Throwable;

/**
 * Delivers a campaign to a recipient as an HTML email. The campaign's sender is used when it
 * has one; a blank `fromAddress` (no `->from()`, no `from-address` default) keeps the mailer's
 * global `mail.from` address and name. A model recipient without a `reachableAt` is recorded
 * as failed (RecipientHasNoAddress).
 */
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
            $address = $this->recipient->reachableAt ?? throw RecipientHasNoAddress::for($this->recipient);

            Mail::html($this->campaign->content, function (Message $message) use ($address): void {
                $message->subject($this->campaign->subject)->to(
                    address: $address,
                    name: $this->recipient->name,
                );

                // A campaign without a sender keeps the mailer's global `mail.from`.
                if ($this->campaign->fromAddress !== '') {
                    $message->from(
                        address: $this->campaign->fromAddress,
                        name: $this->campaign->fromName !== '' ? $this->campaign->fromName : null,
                    );
                }
            });

            $campaigns->campaign($this->campaign)->markProcessed($this->recipient);
        } catch (Throwable $e) {
            $campaigns->campaign($this->campaign)->markFailed($this->recipient, $e->getMessage());
        }
    }
}
