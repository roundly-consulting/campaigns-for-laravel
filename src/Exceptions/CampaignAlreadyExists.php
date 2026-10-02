<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Exceptions;

/**
 * A campaign is being prepared under a uuid another campaign already holds (live or
 * soft-deleted). Preparing never overwrites a campaign: the existing one, its recipients and
 * its batch are left exactly as they were.
 */
final class CampaignAlreadyExists extends CampaignException
{
    public static function withUuid(string $uuid): self
    {
        return new self("A campaign with uuid [{$uuid}] already exists; prepare the new campaign under its own uuid.");
    }
}
