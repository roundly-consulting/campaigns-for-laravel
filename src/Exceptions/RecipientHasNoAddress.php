<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Exceptions;

use RoundlyConsulting\Campaigns\CampaignRecipient;

/**
 * An address-based delivery job (SendCampaignEmail, SendCampaignNotification) was given a model
 * recipient without a `reachableAt`. The job records it as a failed delivery; a campaign of
 * address-less model recipients needs a job that delivers to the model.
 */
final class RecipientHasNoAddress extends CampaignException
{
    public static function for(CampaignRecipient $recipient): self
    {
        return new self("Recipient [{$recipient->uuid}] has no reachableAt address to deliver to. Give it one, or set campaigns.process-recipient-job to a job that delivers to the model.");
    }
}
