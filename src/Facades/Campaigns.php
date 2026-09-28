<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Facades;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignHandle;
use RoundlyConsulting\Campaigns\CampaignManager;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\PendingCampaign;
use RoundlyConsulting\Campaigns\Support\CampaignSettings;
use RoundlyConsulting\Campaigns\Testing\CampaignsFake;

/**
 * @method static PendingCampaign create(string $subject, string $content)
 * @method static Campaign prepare(Campaign $campaign, iterable<CampaignRecipient> $recipients = [])
 * @method static Campaign start(Campaign|string $campaign)
 * @method static Campaign cancel(Campaign|string $campaign)
 * @method static Campaign|null find(string $uuid)
 * @method static Campaign findOrFail(string $uuid)
 * @method static Collection<int, Campaign> all(int $offset = 0, int $limit = 10)
 * @method static CampaignHandle campaign(Campaign|string $campaign)
 * @method static CampaignSettings settings()
 * @method static CampaignsFake fake()
 * @method static void assertCreated(Closure|null $callback = null)
 * @method static void assertNothingCreated()
 * @method static void assertDispatched(Closure|null $callback = null)
 * @method static void assertNothingDispatched()
 * @method static void assertStarted(Campaign|string|null $campaign = null)
 * @method static void assertNothingStarted()
 * @method static void assertCancelled(Campaign|string|null $campaign = null)
 * @method static void assertNothingCancelled()
 * @method static void assertRecipientProcessed(CampaignRecipient|string|null $recipient = null)
 * @method static void assertNothingProcessed()
 * @method static void assertRecipientFailed(CampaignRecipient|string|null $recipient = null, string|null $error = null)
 * @method static void assertNothingFailed()
 *
 * @see CampaignManager
 * @see CampaignsFake
 */
final class Campaigns extends Facade
{
    /**
     * Swap in a recording, in-memory fake behind the facade and the container. Every write —
     * through the facade, an injected manager, the builder, a handle, the cancel command or
     * a delivery job — is recorded instead of run; no batch, job or event is dispatched.
     */
    public static function fake(): CampaignsFake
    {
        $fake = app(CampaignsFake::class);

        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return CampaignManager::class;
    }
}
