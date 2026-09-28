<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Testing;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Assert as PHPUnit;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignManager;
use RoundlyConsulting\Campaigns\CampaignProgress;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Contracts\CampaignStore;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Exceptions\InvalidCampaignTransition;
use RoundlyConsulting\Campaigns\Exceptions\RecipientNotFound;
use RoundlyConsulting\Campaigns\Stores\InMemoryCampaignStore;

/**
 * Test double for the campaigns manager, installed by `Campaigns::fake()`. It extends the
 * manager, so injected managers keep type-checking, and it records every write — through
 * the facade, an injected manager, the `create()` builder, a campaign handle, the
 * `campaigns:cancel` command or a delivery job — instead of running it.
 *
 * It keeps its own in-memory store, so reads (`find()`, `all()`, `campaign()->progress()`,
 * `->recipients()`) answer from what the test created. No batch is opened, no job is
 * queued, no event fires, and the configured store is never touched. It refuses what the
 * real manager refuses (unknown uuid, starting a campaign that is not Pending, a recipient
 * of another campaign).
 */
final class CampaignsFake extends CampaignManager
{
    private readonly InMemoryCampaignStore $memory;

    /** @var list<array{campaign: Campaign, recipients: list<CampaignRecipient>}> */
    private array $created = [];

    /** @var list<string> */
    private array $started = [];

    /** @var list<string> */
    private array $cancelled = [];

    /** @var list<CampaignRecipient> */
    private array $processed = [];

    /** @var list<array{recipient: CampaignRecipient, error: string}> */
    private array $failed = [];

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->memory = new InMemoryCampaignStore;
    }

    public function prepare(Campaign $campaign, iterable $recipients = []): Campaign
    {
        $campaign = clone $campaign;
        $campaign->progress = new CampaignProgress(status: CampaignStatus::Pending);
        $campaign->startedAt = null;
        $campaign->endedAt = null;
        $campaign->batch = null;

        $recipients = CampaignRecipient::scopeAll($recipients, $campaign->uuid);

        $this->memory->save($campaign);
        $this->memory->saveRecipients($campaign->uuid, $recipients);

        $this->created[] = ['campaign' => clone $campaign, 'recipients' => $recipients];

        return $this->findOrFail($campaign->uuid);
    }

    public function start(Campaign|string $campaign): Campaign
    {
        $campaign = $this->findOrFail(self::uuidOf($campaign));

        if ($campaign->progress->status !== CampaignStatus::Pending) {
            throw InvalidCampaignTransition::cannotStart($campaign);
        }

        $this->started[] = $campaign->uuid;

        $campaign->progress->status = CampaignStatus::Processing;
        $campaign->startedAt = Carbon::now();

        $this->memory->save($campaign);

        return $campaign;
    }

    public function cancel(Campaign|string $campaign): Campaign
    {
        $campaign = $this->findOrFail(self::uuidOf($campaign));

        $this->cancelled[] = $campaign->uuid;

        if (! $campaign->progress->status->isTerminal()) {
            $campaign->progress->status = CampaignStatus::Canceled;
            $campaign->endedAt = Carbon::now();

            $this->memory->save($campaign);
        }

        return $campaign;
    }

    /**
     * @internal
     */
    public function markRecipientProcessed(Campaign $campaign, CampaignRecipient $recipient): CampaignRecipient
    {
        self::guard($campaign, $recipient);

        $recipient->hasBeenProcessed = true;
        $recipient->errorOccured = false;
        $recipient->errorMessage = null;

        $this->memory->saveRecipients($campaign->uuid, [$recipient]);
        $this->processed[] = clone $recipient;

        return $recipient;
    }

    /**
     * @internal
     */
    public function markRecipientFailed(Campaign $campaign, CampaignRecipient $recipient, string $error): CampaignRecipient
    {
        self::guard($campaign, $recipient);

        $recipient->hasBeenProcessed = false;
        $recipient->errorOccured = true;
        $recipient->errorMessage = $error;

        $this->memory->saveRecipients($campaign->uuid, [$recipient]);
        $this->failed[] = ['recipient' => clone $recipient, 'error' => $error];

        return $recipient;
    }

    /**
     * A campaign was prepared — by `create()->prepare()`, `create()->dispatch()` or
     * `Campaigns::prepare()`.
     *
     * @param  (Closure(Campaign, list<CampaignRecipient>): bool)|null  $callback
     */
    public function assertCreated(?Closure $callback = null): void
    {
        PHPUnit::assertNotEmpty(
            $this->createdMatching($callback),
            $callback === null ? 'Expected a campaign to be created, but none was.' : 'Expected a matching campaign to be created, but none was.',
        );
    }

    public function assertNothingCreated(): void
    {
        PHPUnit::assertSame([], $this->created, sprintf('Expected no campaign to be created, but %d were.', count($this->created)));
    }

    /**
     * A campaign was created AND started (`create()->dispatch()`, or `prepare()` then `start()`).
     *
     * @param  (Closure(Campaign, list<CampaignRecipient>): bool)|null  $callback
     */
    public function assertDispatched(?Closure $callback = null): void
    {
        PHPUnit::assertNotEmpty(
            $this->dispatchedMatching($callback),
            $callback === null ? 'Expected a campaign to be dispatched, but none was.' : 'Expected a matching campaign to be dispatched, but none was.',
        );
    }

    public function assertNothingDispatched(): void
    {
        $dispatched = $this->dispatchedMatching(null);

        PHPUnit::assertSame([], $dispatched, sprintf('Expected no campaign to be dispatched, but %d were.', count($dispatched)));
    }

    public function assertStarted(Campaign|string|null $campaign = null): void
    {
        PHPUnit::assertTrue(
            self::recorded($this->started, $campaign),
            $campaign === null ? 'Expected a campaign to be started, but none was.' : 'Expected campaign ['.self::uuidOf($campaign).'] to be started.',
        );
    }

    public function assertNothingStarted(): void
    {
        PHPUnit::assertSame([], $this->started, sprintf('Expected no campaign to be started, but %d were.', count($this->started)));
    }

    public function assertCancelled(Campaign|string|null $campaign = null): void
    {
        PHPUnit::assertTrue(
            self::recorded($this->cancelled, $campaign),
            $campaign === null ? 'Expected a campaign to be cancelled, but none was.' : 'Expected campaign ['.self::uuidOf($campaign).'] to be cancelled.',
        );
    }

    public function assertNothingCancelled(): void
    {
        PHPUnit::assertSame([], $this->cancelled, sprintf('Expected no campaign to be cancelled, but %d were.', count($this->cancelled)));
    }

    public function assertRecipientProcessed(CampaignRecipient|string|null $recipient = null): void
    {
        $uuids = array_map(static fn (CampaignRecipient $processed): string => $processed->uuid, $this->processed);

        PHPUnit::assertTrue(
            self::recorded($uuids, $recipient),
            $recipient === null ? 'Expected a recipient to be marked processed, but none was.' : 'Expected recipient ['.self::uuidOf($recipient).'] to be marked processed.',
        );
    }

    public function assertNothingProcessed(): void
    {
        PHPUnit::assertSame([], $this->processed, sprintf('Expected no recipient to be marked processed, but %d were.', count($this->processed)));
    }

    /**
     * @param  string|null  $error  when given, the recorded error must match
     */
    public function assertRecipientFailed(CampaignRecipient|string|null $recipient = null, ?string $error = null): void
    {
        $uuid = $recipient === null ? null : self::uuidOf($recipient);

        $matching = array_filter(
            $this->failed,
            static fn (array $failure): bool => ($uuid === null || $failure['recipient']->uuid === $uuid)
                && ($error === null || $failure['error'] === $error),
        );

        PHPUnit::assertNotEmpty(
            $matching,
            'Expected '.($uuid === null ? 'a recipient' : "recipient [{$uuid}]").' to be marked failed'.($error === null ? '' : " with [{$error}]").'.',
        );
    }

    public function assertNothingFailed(): void
    {
        PHPUnit::assertSame([], $this->failed, sprintf('Expected no recipient to be marked failed, but %d were.', count($this->failed)));
    }

    protected function store(): CampaignStore
    {
        return $this->memory;
    }

    /**
     * @param  (Closure(Campaign, list<CampaignRecipient>): bool)|null  $callback
     * @return list<array{campaign: Campaign, recipients: list<CampaignRecipient>}>
     */
    private function createdMatching(?Closure $callback): array
    {
        return array_values(array_filter(
            $this->created,
            static fn (array $entry): bool => $callback === null || $callback($entry['campaign'], $entry['recipients']) === true,
        ));
    }

    /**
     * @param  (Closure(Campaign, list<CampaignRecipient>): bool)|null  $callback
     * @return list<array{campaign: Campaign, recipients: list<CampaignRecipient>}>
     */
    private function dispatchedMatching(?Closure $callback): array
    {
        return array_values(array_filter(
            $this->createdMatching($callback),
            fn (array $entry): bool => in_array($entry['campaign']->uuid, $this->started, true),
        ));
    }

    /**
     * @param  list<string>  $uuids
     */
    private static function recorded(array $uuids, Campaign|CampaignRecipient|string|null $subject): bool
    {
        return $subject === null ? $uuids !== [] : in_array(self::uuidOf($subject), $uuids, true);
    }

    private static function uuidOf(Campaign|CampaignRecipient|string $subject): string
    {
        return is_string($subject) ? $subject : $subject->uuid;
    }

    private static function guard(Campaign $campaign, CampaignRecipient $recipient): void
    {
        if (! $recipient->belongsTo($campaign->uuid)) {
            throw RecipientNotFound::inCampaign($campaign->uuid, $recipient->uuid);
        }
    }
}
