<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignManager;
use RoundlyConsulting\Campaigns\PendingCampaign;

/**
 * @method static PendingCampaign create(string $subject, string $content)
 * @method static Campaign|null find(string $uuid)
 * @method static Campaign findOrFail(string $uuid)
 * @method static void cancel(string $uuid)
 * @method static void each(Closure $callback, int $offset = 0, int $limit = 10)
 *
 * @see CampaignManager
 */
final class Campaigns extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CampaignManager::class;
    }
}
