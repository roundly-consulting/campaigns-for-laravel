<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Exceptions;

/**
 * The recipient is not part of the campaign — unknown, or a recipient of another campaign.
 * A campaign handle never reads or writes another campaign's recipients.
 */
final class RecipientNotFound extends CampaignException
{
    public static function inCampaign(string $campaignUuid, string $recipientUuid): self
    {
        return new self("No recipient [{$recipientUuid}] in campaign [{$campaignUuid}].");
    }
}
