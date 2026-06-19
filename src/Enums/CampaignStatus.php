<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Enums;

enum CampaignStatus: string
{
    case Created = 'Created';
    case Pending = 'Pending';
    case Processing = 'Processing';
    case Completed = 'Completed';
    case Failed = 'Failed';
    case Canceled = 'Canceled';
}
