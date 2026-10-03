<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/campaigns-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=campaigns-for-laravel">
    <img src="art/hero.png" alt="Campaigns for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/campaigns-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/campaigns-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/campaigns-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/campaigns-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/campaigns-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/campaigns-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=campaigns-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Campaigns for Laravel

Send campaigns to many recipients using Laravel queues and job batches. The package is
storage-agnostic: it ships an in-memory store out of the box and an optional, opt-in
database-backed store, and it lets you swap the per-recipient delivery job so the same
campaign engine can deliver email, SMS, push notifications, or Laravel notifications.

## Requirements

- PHP `^8.4`
- Laravel `^12.0` or `^13.0`

## Installation

Install via Composer:

```bash
composer require roundly-consulting/campaigns-for-laravel
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="campaigns-config"
```

Campaigns builds on three sibling packages (see **Integrates with** below), all pulled in
automatically as dependencies: `contacts-for-laravel`, `options-for-laravel`, and
`enums-for-laravel`. Migrations are **not loaded automatically** — each package publishes
them and your app owns them.

A few tables are required even with the default in-memory store:

- **`options`** (options-for-laravel) — every `prepare()` / `dispatch()` reads the send
  defaults through it;
- **`contacts`** (contacts-for-laravel) — when you address campaigns from contact records
  or contact owners;
- **`job_batches`** (Laravel) — every campaign runs as a job batch. New Laravel apps already
  create it in their default `create_jobs_table` migration; otherwise generate it with
  `php artisan make:queue-batches-table`.

```bash
php artisan vendor:publish --tag="options-migrations"
php artisan vendor:publish --tag="contacts-migrations"
php artisan migrate
```

Only the database store (see **Persistence**) needs the package's own two tables:

```bash
php artisan vendor:publish --tag="campaigns-migrations"
php artisan migrate
```

## Configuration

The published config file (`config/campaigns.php`):

```php
return [
    'store' => env('CAMPAIGNS_STORE', \RoundlyConsulting\Campaigns\Stores\InMemoryCampaignStore::class),
    'from-name' => env('CAMPAIGNS_FROM_NAME', ''),
    'from-address' => env('CAMPAIGNS_FROM_ADDRESS', ''),
    'recipients' => [
        'only-verified' => env('CAMPAIGNS_ONLY_VERIFIED', false),
        'contact-type' => env('CAMPAIGNS_RECIPIENT_CONTACT_TYPE', 'email'),
    ],
    'sending-queue' => env('CAMPAIGNS_SENDING_QUEUE', 'default'),
    'process-recipient-job' => \RoundlyConsulting\Campaigns\Jobs\SendCampaignEmail::class,
    'notification' => env('CAMPAIGNS_NOTIFICATION'),
    'notification-channel' => env('CAMPAIGNS_NOTIFICATION_CHANNEL', 'mail'),
];
```

| Key | Type | Default | Description |
|---|---|---|---|
| `store` | `class-string` | `InMemoryCampaignStore::class` (env `CAMPAIGNS_STORE`) | Where campaigns and recipients are kept — a `Contracts\CampaignStore`. Set to `Stores\DatabaseCampaignStore::class` to persist campaigns, or point at your own. |
| `from-name` | `string` | `''` (env `CAMPAIGNS_FROM_NAME`) | Default sender name used when a campaign is dispatched without `->from()`. Seeds the `DefaultFromName` option. |
| `from-address` | `string` | `''` (env `CAMPAIGNS_FROM_ADDRESS`) | Default sender address used when a campaign is dispatched without `->from()`. Seeds the `DefaultFromAddress` option. Left blank, `SendCampaignEmail` sends from your mailer's global `mail.from` address and name. |
| `recipients.only-verified` | `bool` | `false` (env `CAMPAIGNS_ONLY_VERIFIED`) | Skip owners/contacts without a verified contact when resolving recipients. Seeds the `OnlyVerifiedRecipients` option. Read strictly: `true`/`1`/`on`/`yes` or `false`/`0`/`off`/`no`; anything else throws `InvalidConfigurationException`. |
| `recipients.contact-type` | `string` | `email` (env `CAMPAIGNS_RECIPIENT_CONTACT_TYPE`) | Contact kind resolved for an owner when `->viaContactType()` is unset. Seeds the `DefaultRecipientContactType` option. |
| `sending-queue` | `string` | `default` (env `CAMPAIGNS_SENDING_QUEUE`) | Queue every per-recipient delivery job runs on — point a worker at it (`php artisan queue:work --queue=…`). The campaign's job batch is opened on it when the campaign is prepared (a Laravel batch pushes all of its jobs onto one queue). Seeds the `DefaultSendingQueue` option. |
| `process-recipient-job` | `class-string` | `SendCampaignEmail::class` | The job dispatched once per recipient. Must implement `Contracts\ProcessesCampaignRecipient`. |
| `notification` | `class-string\|null` | `null` (env `CAMPAIGNS_NOTIFICATION`) | A `Notifications\CampaignNotification` subclass delivered by `SendCampaignNotification`. |
| `notification-channel` | `string` | `mail` (env `CAMPAIGNS_NOTIFICATION_CHANNEL`) | Routing channel for `SendCampaignNotification`'s on-demand notifiable. Seeds the `DefaultChannel` option. |

The send defaults above are also **DB-backed and runtime-editable** through
`options-for-laravel` — the config value is the seed/fallback, a stored option overrides it.
See **Integrates with**.

## Usage

### The `Campaigns` facade

Create, address and send a campaign with the fluent builder. UUIDs are generated for you.

```php
use RoundlyConsulting\Campaigns\Facades\Campaigns;

$campaign = Campaigns::create('Buy today and spend less', '<p>Hello dear customer!</p>')
    ->from('no-reply@eshop.tld', 'Best E-Shop Ever')
    ->to(['john@doe.tld', 'jane@doe.tld'])
    ->dispatch();                    // prepare + start in one call
```

`->to()` is additive and accepts an email/route string, a `CampaignRecipient`, a
`contacts-for-laravel` `Contact` record, a `HasContacts` owner model, or any iterable of
those (see **Integrates with**). Each address is added once: one that is already on the
campaign (compared case-insensitively) is skipped, however it arrives. `->uuid()`,
`->subject()` and `->content()` override the builder's defaults; a uuid another campaign
already holds is refused with `CampaignAlreadyExists` — preparing never overwrites (or
re-sends) an existing campaign.

Without `->from()` (and without a `from-address` default) the email goes out from your
mailer's global `mail.from` address.

Prepare now, send later:

```php
$campaign = Campaigns::create('Spring sale', '<p>50% off</p>')->to($subscribers)->prepare();

// … later, e.g. from a scheduled job or an admin action
Campaigns::start($campaign->uuid);
```

Only a prepared (`Pending`) campaign starts: starting one that is already sending — or has
ended — throws `InvalidCampaignTransition` instead of queueing every recipient twice. That
holds when two processes start it at the same moment too (a double-clicked button, two
scheduler workers): the move to `Processing` is one atomic compare-and-set, and only its
winner queues the recipients. A campaign with nobody to send to (no recipients, or every
owner filtered out) completes as soon as it starts.

Work with one campaign through its handle:

```php
$campaign = Campaigns::campaign($uuid);   // throws CampaignNotFound for an unknown uuid

$campaign->progress()->percentage();      // share delivered so far: 42.5
$campaign->progress()->failed;            // deliveries that failed
$campaign->progress()->remaining();       // recipients with no outcome yet
$campaign->recipients();                  // Collection<CampaignRecipient>, in the order added
$campaign->recipients(offset: 100, limit: 50);
$campaign->recipient($recipientUuid);     // one recipient of THIS campaign
$campaign->batch();                       // the Illuminate\Bus\Batch, or null
$campaign->start();
$campaign->cancel();                      // a campaign that already ended is left as it is
```

A handle is scoped to its campaign: `recipient()`, `markProcessed()` and `markFailed()`
throw `RecipientNotFound` for a recipient of another campaign.

Progress counts each recipient once: `sent` were delivered, `failed` failed — recorded as
failed by the delivery job, or the job itself failed (threw, timed out, ran out of attempts)
— and the rest are still to go. A failed delivery is never counted as sent, and it never ends
the campaign early: the other recipients keep sending and the campaign stays cancellable.
Once every job ran, the campaign is `Completed` — or `Failed` when not one delivery got
through.

The rest of the facade:

| Method | Returns | Description |
|---|---|---|
| `create(string $subject, string $content)` | `PendingCampaign` | Fluent builder (`from`, `to`, `onlyVerified`, `viaContactType`, `uuid`, `subject`, `content`, `prepare`, `dispatch`). |
| `prepare(Campaign $campaign, iterable $recipients = [])` | `Campaign` | Store a campaign built from the value object with its recipients, and leave it `Pending`. Throws `CampaignAlreadyExists` for a uuid that is taken. |
| `start(Campaign\|string $campaign)` | `Campaign` | Start sending a `Pending` campaign. |
| `cancel(Campaign\|string $campaign)` | `Campaign` | Cancel a campaign and its batch. |
| `find(string $uuid)` | `?Campaign` | Look up a campaign (live progress while it sends). |
| `findOrFail(string $uuid)` | `Campaign` | Look up or throw `CampaignNotFound`. |
| `all(int $offset = 0, int $limit = 10)` | `Collection<int, Campaign>` | A page of campaigns in creation order. |
| `campaign(Campaign\|string $campaign)` | `CampaignHandle` | One campaign: `uuid`, `get`, `progress`, `recipients`, `recipient`, `batch`, `start`, `cancel`, `markProcessed`, `markFailed`. |
| `settings()` | `CampaignSettings` | The effective send defaults: `fromName`, `fromAddress`, `notificationChannel`, `sendingQueue`, `onlyVerifiedRecipients`, `defaultRecipientContactType`. |

Recipients you add get a `campaignUuid`. Adding a recipient that already belongs to another
campaign adds a fresh copy (new uuid, clean delivery state) — it is never moved out of the
campaign it came from.

### Without the facade

The facade is sugar over `RoundlyConsulting\Campaigns\CampaignManager`. Inject it for the
same API:

```php
use RoundlyConsulting\Campaigns\CampaignManager;

final class LaunchSpringSale
{
    public function __construct(private CampaignManager $campaigns) {}

    public function __invoke(iterable $subscribers): void
    {
        $campaign = $this->campaigns->create('Spring sale', '<p>50% off</p>')->to($subscribers)->prepare();

        $this->campaigns->start($campaign);
    }
}
```

Or call an action directly — every write is one:

```php
use Illuminate\Support\Str;
use RoundlyConsulting\Campaigns\Actions\CancelCampaignAction;
use RoundlyConsulting\Campaigns\Actions\PrepareCampaignAction;
use RoundlyConsulting\Campaigns\Actions\StartCampaignAction;
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;

$campaign = app(PrepareCampaignAction::class)->execute(
    new Campaign(uuid: (string) Str::uuid(), subject: 'Spring sale', content: '<p>50% off</p>', fromName: 'Shop', fromAddress: 'no-reply@shop.tld'),
    [new CampaignRecipient(uuid: (string) Str::uuid(), name: 'John Doe', reachableAt: 'john@doe.tld')],
);

app(StartCampaignAction::class)->execute($campaign);
app(CancelCampaignAction::class)->execute($campaign->uuid);
```

| Action | Facade |
|---|---|
| `PrepareCampaignAction` | `Campaigns::prepare()`, `create()->prepare()` |
| `StartCampaignAction` | `Campaigns::start()`, `campaign()->start()`, `create()->dispatch()` |
| `CancelCampaignAction` | `Campaigns::cancel()`, `campaign()->cancel()`, `campaigns:cancel` |
| `MarkRecipientProcessedAction` | `Campaigns::campaign()->markProcessed()` |
| `MarkRecipientFailedAction` | `Campaigns::campaign()->markFailed()` |

### Testing your code: `Campaigns::fake()`

```php
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\Facades\Campaigns;

Campaigns::fake();

// … run the code under test …

Campaigns::assertDispatched(fn (Campaign $campaign, array $recipients) => $campaign->subject === 'Spring sale'
    && count($recipients) === 2);
Campaigns::assertCancelled($uuid);
Campaigns::assertNothingStarted();
```

The fake extends `CampaignManager`, so injected managers, the `campaigns:cancel` command and
delivery jobs all see it. It records every write instead of running it — no batch, no queued
job, no event, nothing written to your configured store — and keeps its own in-memory store,
so `find()`, `all()` and `campaign()->progress()/recipients()` answer from what the test
created. It still refuses what the real manager refuses (unknown uuid, a uuid that is
already taken, starting a campaign that is not `Pending`, a recipient of another campaign).

| Assertion | Passes when |
|---|---|
| `assertCreated(?Closure $callback)` / `assertNothingCreated()` | a campaign was prepared (`create()->prepare()`, `->dispatch()`, `Campaigns::prepare()`); the callback gets the `Campaign` and its `list<CampaignRecipient>` |
| `assertDispatched(?Closure $callback)` / `assertNothingDispatched()` | a campaign was created **and** started |
| `assertStarted(Campaign\|string\|null $campaign)` / `assertNothingStarted()` | `start()` ran (flat, handle or `dispatch()`) |
| `assertCancelled(Campaign\|string\|null $campaign)` / `assertNothingCancelled()` | `cancel()` ran (flat, handle or the command) |
| `assertRecipientProcessed(CampaignRecipient\|string\|null $recipient)` / `assertNothingProcessed()` | a delivery was recorded — e.g. by running your job's `handle()` under the fake |
| `assertRecipientFailed(CampaignRecipient\|string\|null $recipient, ?string $error)` / `assertNothingFailed()` | a failed delivery was recorded |

### Value objects

- `Campaign` — `uuid`, `subject`, `content`, `fromName`, `fromAddress`, `progress`,
  `startedAt`, `endedAt`, `batch`, and `toArray()`.
- `CampaignProgress` — `status`, `sent` (delivered), `failed`, `pending` (no outcome yet),
  `total`, plus `percentage()` (share delivered, 0–100), `remaining()`, `isRunning()`,
  `isComplete()`, and `toArray()`.
- `CampaignRecipient` — `uuid`, `name`, `reachableAt`, `hasBeenProcessed`, `errorOccured`,
  `errorMessage` (`null` when no error), `campaignUuid` (set once it is added to a campaign),
  `belongsTo($campaignUuid)`, and `toArray()`.
- Exceptions (all extend `Exceptions\CampaignException`): `CampaignNotFound`,
  `CampaignAlreadyExists`, `RecipientNotFound`, `InvalidCampaignTransition`.
- `Enums\CampaignStatus` — `Created`, `Pending`, `Processing`, `Completed`, `Failed`,
  `Canceled`, plus `isTerminal()`. It adopts the `enums-for-laravel` `Helpers` trait, so you
  also get `CampaignStatus::values()`, `::labels()`, `::options()`, `::toOptions()`,
  `::validationRule()`, `->readable()`/`->label()`, and case lookups
  (`tryFromName()`, `hasValue()`, …).

### Events

Listen to lifecycle and recipient events to drive logging, dashboards, metrics, or webhooks:

- `Events\CampaignPrepared`, `Events\CampaignStarted`, `Events\CampaignCompleted`,
  `Events\CampaignFailed`, `Events\CampaignCancelled` — each carries the `Campaign`.
  `CampaignCompleted` fires once every delivery job ran (some may have failed — see
  `progress->failed`); `CampaignFailed` fires instead when not one delivery got through.
- `Events\RecipientProcessed` — carries the `Campaign` and `CampaignRecipient`.
- `Events\RecipientFailed` — carries the `Campaign`, `CampaignRecipient`, and `error` string.

### Choosing a delivery channel

Each recipient is processed by the job at `campaigns.process-recipient-job`. Two are shipped,
and both implement `Contracts\ProcessesCampaignRecipient`:

- **`Jobs\SendCampaignEmail`** (default) — sends the campaign content as an email.
- **`Jobs\SendCampaignNotification`** — delivers via Laravel's notification system. Point
  `campaigns.notification` at a `Notifications\CampaignNotification` subclass (constructed with
  the `Campaign` and `CampaignRecipient`) and set `campaigns.notification-channel` to the route
  channel (e.g. `mail`, `vonage`, `fcm`).

```php
use RoundlyConsulting\Campaigns\Notifications\CampaignNotification;

final class ProductLaunchNotification extends CampaignNotification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable)
    {
        return (new \Illuminate\Notifications\Messages\MailMessage)->line($this->campaign->content);
    }
}
```

To deliver via SMS, push, or any other transport, implement
`Contracts\ProcessesCampaignRecipient` in your own job and set `campaigns.process-recipient-job`
to it. The job is constructed with the `Campaign` and `CampaignRecipient`, and its `handle()`
receives the `CampaignManager` to record the outcome:

```php
use RoundlyConsulting\Campaigns\CampaignManager;

public function handle(CampaignManager $campaigns): void
{
    if ($this->batch()?->cancelled()) {
        return; // the campaign was cancelled
    }

    try {
        $this->sms->send($this->recipient->reachableAt, $this->campaign->content);

        $campaigns->campaign($this->campaign)->markProcessed($this->recipient);
    } catch (Throwable $e) {
        $campaigns->campaign($this->campaign)->markFailed($this->recipient, $e->getMessage());
    }
}
```

Record the outcome, or let the job fail — a job that fails outright counts as a failed
delivery while the other recipients keep sending — but not both, or the recipient counts as
failed twice. The early return on a cancelled batch (the shipped jobs do the same) is what
stops a cancelled campaign from sending.

Under `Campaigns::fake()` those calls are recorded, so `assertRecipientProcessed()` /
`assertRecipientFailed()` test your job.

### Persistence (optional database store)

`Stores\InMemoryCampaignStore` (default) keeps campaigns in memory for the current request,
console command or queued job — ideal for tests and create-and-send flows. Nothing survives
it: the next request or job (and a queue worker in another process) starts empty, so
deliveries and events still happen in the worker, but progress is only tracked where the
campaign was created. For persisted campaigns (delayed starts, dashboards, retries, audit),
switch to the shipped database store with one config change:

```dotenv
CAMPAIGNS_STORE="RoundlyConsulting\Campaigns\Stores\DatabaseCampaignStore"
```

Then publish and run the migrations (see Installation — they are never auto-loaded, so a bare
`php artisan migrate` will not create the tables until you publish). The database store keeps campaigns
in `CampaignRecord` / `CampaignRecipientRecord` Eloquent models and behaves like the in-memory
store (one shared contract test suite runs against both) — including keying recipients by
campaign, so the same recipient added to two campaigns is kept in both. A store is persistence
only — `find`, `all`, `insert`, `save`, `saveIfStatus`, `saveRecipients`, `recipients`,
`findRecipient`, `countRecipients` — while batches, status changes and events live in the
actions, so you can implement `Contracts\CampaignStore` against any storage and point
`campaigns.store` at it. `insert()` (refuse a taken uuid) and `saveIfStatus()` (write only
while the stored status is the expected one) must each be one atomic step: they are what make
duplicate uuids and racing starts safe.

### Console commands

List campaigns and their progress:

```bash
php artisan campaigns:list --offset=0 --limit=10
```

Cancel a campaign by uuid:

```bash
php artisan campaigns:cancel {uuid}
```

| Command | Argument / option | Description |
|---|---|---|
| `campaigns:list` | `--offset=0`, `--limit=10` | Paginated table of campaigns and progress (delivered share, sent, failed, still to send). |
| `campaigns:cancel` | `{uuid}` | Cancel a campaign and its batch (`Campaigns::cancel()`); non-zero exit on unknown uuid, a warning for a campaign that already ended. |

## Integrates with

Campaigns hard-depends on three sibling packages and builds real features on them.

### `contacts-for-laravel` — recipient resolution

Address a campaign straight from your existing contact records instead of copy-pasting email
strings. `->to()` accepts a `Contact` model or any `HasContacts` owner and resolves a reachable
recipient (auto-filling the display name from the owner/label):

```php
use RoundlyConsulting\Campaigns\Facades\Campaigns;
use RoundlyConsulting\Contacts\Enums\ContactType;

Campaigns::create('Spring sale', '<p>50% off</p>')
    ->onlyVerified()                    // skip owners/contacts without a verified contact
    ->viaContactType(ContactType::Email) // pick the contact kind (email, phone, …)
    ->to($user)                          // HasContacts owner → its primary email
    ->to($contact)                       // a Contact record → its value
    ->to(User::active()->get())          // an iterable of owners
    ->dispatch();
```

Owners (or contacts) with no matching — or no verified — contact of the send kind are silently
skipped, never fatal. `$user` gets one email even when `User::active()->get()` includes them
again: an address already on the campaign is not added twice.
`->viaContactType(ContactType::Phone)` resolves phone contacts, which pairs with
`SendCampaignNotification` for SMS. Plain strings and `CampaignRecipient` instances still work
unchanged.

### `options-for-laravel` — typed, DB-backed send defaults

The static send defaults are exposed as typed, runtime-editable option classes under
`RoundlyConsulting\Campaigns\Options`: `DefaultFromName`, `DefaultFromAddress`, `DefaultChannel`,
`DefaultSendingQueue`, `OnlyVerifiedRecipients`, and `DefaultRecipientContactType`. Each falls back to its `config/campaigns.php` value until an
option is stored, then the stored value wins:

```php
use RoundlyConsulting\Campaigns\Options\DefaultFromAddress;
use RoundlyConsulting\Campaigns\Options\OnlyVerifiedRecipients;
use RoundlyConsulting\Options\Facades\Options;

Options::set(DefaultFromAddress::class, 'news@acme.test');
Options::set(OnlyVerifiedRecipients::class, true);

// A from-less campaign now sends from news@acme.test, and to($owner)
// skips unverified contacts by default.
Campaigns::create('Subject', 'Body')->to($user)->dispatch();
```

Explicit builder calls (`->from()`, `->onlyVerified()`, `->viaContactType()`) always override
the stored options.

### `enums-for-laravel` — `CampaignStatus` helpers

`CampaignStatus` uses the shared `Helpers` trait for `values()`/`labels()`/`options()`/
`toOptions()`/`validationRule()`/`readable()` and case lookups on top of its own
`isTerminal()`.

## Testing

```bash
composer test
```

To test code that uses this package, see **Testing your code: `Campaigns::fake()`** above.

## Changelog

See [CHANGELOG](CHANGELOG.md).

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=campaigns-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=campaigns-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
