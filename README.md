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
</p>
<!-- roundly-badges:end -->

# Campaigns for Laravel

Send campaigns to many recipients using Laravel queues and job batches. The package is
storage-agnostic: it ships an in-memory manager out of the box and an optional, opt-in
database-backed manager, and it lets you swap the per-recipient delivery job so the same
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

Migrations are **not loaded automatically** — the package publishes them and your app owns
them. The package works with **zero database setup** by default (the in-memory manager);
only if you switch to the database manager do you need its two tables:

```bash
php artisan vendor:publish --tag="campaigns-migrations"
php artisan migrate
```

Campaigns builds on three sibling packages (see **Integrates with** below), all pulled in
automatically as dependencies: `contacts-for-laravel`, `options-for-laravel`, and
`enums-for-laravel`. Their migrations are publish-only too — publish and run them so
recipient resolution and DB-backed send defaults work:

```bash
php artisan vendor:publish --tag="options-migrations"
php artisan vendor:publish --tag="contacts-migrations"
php artisan migrate
```

## Configuration

The published config file (`config/campaigns.php`):

```php
return [
    'manager' => env('CAMPAIGNS_MANAGER', \RoundlyConsulting\Campaigns\Managers\InMemoryManager::class),
    'from-name' => env('CAMPAIGNS_FROM_NAME', ''),
    'from-address' => env('CAMPAIGNS_FROM_ADDRESS', ''),
    'recipients' => [
        'only-verified' => env('CAMPAIGNS_ONLY_VERIFIED', false),
        'contact-type' => env('CAMPAIGNS_RECIPIENT_CONTACT_TYPE', 'email'),
    ],
    'batch-queue' => env('CAMPAIGNS_BATCH_QUEUE', 'default'),
    'sending-queue' => env('CAMPAIGNS_SENDING_QUEUE', 'default'),
    'process-recipient-job' => \RoundlyConsulting\Campaigns\Jobs\SendCampaignEmail::class,
    'notification' => env('CAMPAIGNS_NOTIFICATION'),
    'notification-channel' => env('CAMPAIGNS_NOTIFICATION_CHANNEL', 'mail'),
];
```

| Key | Type | Default | Description |
|---|---|---|---|
| `manager` | `class-string` | `InMemoryManager::class` (env `CAMPAIGNS_MANAGER`) | The `Manager` implementation bound in the container. Set to `DatabaseManager::class` to persist campaigns, or point at your own. |
| `from-name` | `string` | `''` (env `CAMPAIGNS_FROM_NAME`) | Default sender name used when a campaign is dispatched without `->from()`. Seeds the `DefaultFromName` option. |
| `from-address` | `string` | `''` (env `CAMPAIGNS_FROM_ADDRESS`) | Default sender address used when a campaign is dispatched without `->from()`. Seeds the `DefaultFromAddress` option. |
| `recipients.only-verified` | `bool` | `false` (env `CAMPAIGNS_ONLY_VERIFIED`) | Skip owners/contacts without a verified contact when resolving recipients. Seeds the `OnlyVerifiedRecipients` option. |
| `recipients.contact-type` | `string` | `email` (env `CAMPAIGNS_RECIPIENT_CONTACT_TYPE`) | Contact kind resolved for an owner when `->viaContactType()` is unset. Seeds the `DefaultRecipientContactType` option. |
| `batch-queue` | `string` | `default` (env `CAMPAIGNS_BATCH_QUEUE`) | Queue used for the campaign batch. Seeds the `DefaultBatchQueue` option. |
| `sending-queue` | `string` | `default` (env `CAMPAIGNS_SENDING_QUEUE`) | Queue used for each per-recipient processing job. Seeds the `DefaultSendingQueue` option. |
| `process-recipient-job` | `class-string` | `SendCampaignEmail::class` | The job dispatched once per recipient. Must implement `Contracts\ProcessesCampaignRecipient`. |
| `notification` | `class-string\|null` | `null` (env `CAMPAIGNS_NOTIFICATION`) | A `Notifications\CampaignNotification` subclass delivered by `SendCampaignNotification`. |
| `notification-channel` | `string` | `mail` (env `CAMPAIGNS_NOTIFICATION_CHANNEL`) | Routing channel for `SendCampaignNotification`'s on-demand notifiable. Seeds the `DefaultChannel` option. |

The send defaults above are also **DB-backed and runtime-editable** through
`options-for-laravel` — the config value is the seed/fallback, a stored option overrides it.
See **Integrates with**.

## Usage

### The `Campaigns` facade (recommended)

The fastest way to create, address, and send a campaign is the fluent builder behind the
`Campaigns` facade. UUIDs are generated for you.

```php
use RoundlyConsulting\Campaigns\Facades\Campaigns;

$campaign = Campaigns::create('Buy today and spend less', '<p>Hello dear customer!</p>')
    ->from('no-reply@eshop.tld', 'Best E-Shop Ever')
    ->to(['john@doe.tld', 'jane@doe.tld'])
    ->dispatch();

// Inspect progress later.
$campaign = Campaigns::find($campaign->uuid);
$campaign->progress->percentage();   // float, e.g. 42.5
$campaign->progress->remaining();    // int recipients left
$campaign->progress->isRunning();    // bool
$campaign->progress->isComplete();   // bool

// Cancel a running campaign and its batch.
Campaigns::cancel($campaign->uuid);
```

`->to()` is additive and accepts an email/route string, a `CampaignRecipient`, a
`contacts-for-laravel` `Contact` record, a `HasContacts` owner model, or any iterable of
those (see **Integrates with**). Use `->prepare()` instead of `->dispatch()` to create the
batch and leave the campaign `Pending` without sending. `->uuid()`, `->subject()`, and
`->content()` override the builder's defaults.

### The low-level `Manager`

The facade is sugar over the configured `Manager`, which you can still drive directly:

```php
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Managers\Manager;

/** @var Manager $manager */
$manager = resolve(Manager::class);

$manager->prepare(new Campaign(
    uuid: 'e184d08f-5081-4fbb-9604-7a2460c3fb3a',
    subject: 'Buy today and spend less',
    content: 'Hello dear customer!',
    fromName: 'Best E-Shop Ever',
    fromAddress: 'no-reply@eshop.tld',
));

$manager->pushRecipientsToCampaign('e184d08f-5081-4fbb-9604-7a2460c3fb3a', [
    new CampaignRecipient(uuid: '7f3eb754-709e-4837-b82a-49c83223eed6', name: 'John Doe', reachableAt: 'john@doe.tld'),
]);

$manager->start('e184d08f-5081-4fbb-9604-7a2460c3fb3a');
```

`start()`, `cancel()`, and `findOrFail()` throw `Exceptions\CampaignNotFound` when the uuid is
unknown; `find()` returns `null`.

### Value objects

- `Campaign` — `uuid`, `subject`, `content`, `fromName`, `fromAddress`, `progress`,
  `startedAt`, `endedAt`, `batch`, and `toArray()`.
- `CampaignProgress` — `status`, `sent`, `pending`, `total`, plus `percentage()`,
  `remaining()`, `isRunning()`, `isComplete()`, and `toArray()`.
- `CampaignRecipient` — `uuid`, `name`, `reachableAt`, `hasBeenProcessed`, `errorOccured`,
  `errorMessage` (`null` when no error), and `toArray()`.
- `Enums\CampaignStatus` — `Created`, `Pending`, `Processing`, `Completed`, `Failed`,
  `Canceled`, plus `isTerminal()`. It adopts the `enums-for-laravel` `Helpers` trait, so you
  also get `CampaignStatus::values()`, `::labels()`, `::options()`, `::toOptions()`,
  `::validationRule()`, `->readable()`/`->label()`, and case lookups
  (`tryFromName()`, `hasValue()`, …).

### Events

Listen to lifecycle and recipient events to drive logging, dashboards, metrics, or webhooks:

- `Events\CampaignPrepared`, `Events\CampaignStarted`, `Events\CampaignCompleted`,
  `Events\CampaignFailed`, `Events\CampaignCancelled` — each carries the `Campaign`.
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
to it. The job is constructed with the `Campaign` and `CampaignRecipient` and should call
`markRecipientAsProcessed()` / `markRecipientAsFailed()` on the injected `Manager`.

### Persistence (optional database manager)

`InMemoryManager` (default) keeps campaigns in static arrays — ideal for tests and
create-and-send flows. For persisted campaigns (delayed starts, dashboards, retries, audit),
switch to the shipped database manager with one config change:

```dotenv
CAMPAIGNS_MANAGER="RoundlyConsulting\Campaigns\Managers\DatabaseManager"
```

Then publish and run the migrations (see Installation — they are never auto-loaded, so a bare
`php artisan migrate` will not create the tables until you publish). The database manager stores campaigns
in `CampaignRecord` / `CampaignRecipientRecord` Eloquent models and behaves identically to the
in-memory manager (the two are proven equivalent by a shared contract test suite). You can
also implement `Managers\Manager` yourself against any storage and point `campaigns.manager`
at it.

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
| `campaigns:list` | `--offset=0`, `--limit=10` | Paginated table of campaigns and progress. |
| `campaigns:cancel` | `{uuid}` | Cancel a campaign and its batch; non-zero exit on unknown uuid. |

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
skipped, never fatal. `->viaContactType(ContactType::Phone)` resolves phone contacts, which
pairs with `SendCampaignNotification` for SMS. Plain strings and `CampaignRecipient` instances
still work unchanged.

### `options-for-laravel` — typed, DB-backed send defaults

The static send defaults are exposed as typed, runtime-editable option classes under
`RoundlyConsulting\Campaigns\Options`: `DefaultFromName`, `DefaultFromAddress`, `DefaultChannel`,
`DefaultBatchQueue`, `DefaultSendingQueue`, `OnlyVerifiedRecipients`, and
`DefaultRecipientContactType`. Each falls back to its `config/campaigns.php` value until an
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

## Changelog

See [CHANGELOG](CHANGELOG.md).

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=campaigns-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation or a
monthly pledge on Patreon helps fund maintenance, new features and new packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
