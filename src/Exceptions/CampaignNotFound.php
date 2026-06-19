<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Exceptions;

final class CampaignNotFound extends CampaignException
{
    public static function withUuid(string $uuid): self
    {
        return new self("No campaign found for uuid [{$uuid}].");
    }
}
