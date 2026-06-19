# Campaigns for Laravel

Send campaigns to multiple recipients using Laravel queues and job batches. The package is
storage-agnostic: it ships an in-memory manager out of the box and lets you swap in your own
manager and per-recipient processing job, so you can deliver email, SMS, push notifications,
or Laravel notifications across many channels.

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

The package ships no migrations and no models — it is deliberately storage-agnostic.

## Configuration

The published config file (`config/campaigns.php`):

```php
<?php

declare(strict_types=1);

return [
    'manager' => \RoundlyConsulting\Campaigns\Managers\InMemoryManager::class,

    'batch-queue' => env('CAMPAIGNS_BATCH_QUEUE', 'default'),

    'sending-queue' => env('CAMPAIGNS_SENDING_QUEUE', 'default'),

    'process-recipient-job' => \RoundlyConsulting\Campaigns\Jobs\SendCampaignEmail::class,
];
```

| Key | Type | Default | Description |
|---|---|---|---|
| `manager` | `class-string` | `InMemoryManager::class` | The `Manager` implementation bound in the container. Replace it with your own (e.g. a database-backed manager). |
| `batch-queue` | `string` | `default` (env `CAMPAIGNS_BATCH_QUEUE`) | Queue used for the campaign batch. |
| `sending-queue` | `string` | `default` (env `CAMPAIGNS_SENDING_QUEUE`) | Queue used for each per-recipient processing job. |
| `process-recipient-job` | `class-string` | `SendCampaignEmail::class` | The job dispatched once per recipient. Swap it to send SMS, push, notifications, etc. |

Environment variables:

- `CAMPAIGNS_BATCH_QUEUE` — overrides `batch-queue`.
- `CAMPAIGNS_SENDING_QUEUE` — overrides `sending-queue`.

## Usage

Resolve the configured `Manager` from the container and drive a campaign through it:

```php
use RoundlyConsulting\Campaigns\Campaign;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Campaigns\Managers\Manager;

/** @var Manager $manager */
$manager = resolve(Manager::class);

// Prepare a campaign (creates the batch and sets it to Pending).
$manager->prepare(new Campaign(
    uuid: 'e184d08f-5081-4fbb-9604-7a2460c3fb3a',
    subject: 'Buy today and spend less',
    content: 'Hello dear customer, buy our product and spend even more!',
    fromName: 'Best E-Shop Ever',
    fromAddress: 'no-reply@eshop.tld',
));

// Push recipients (can be called multiple times).
$manager->pushRecipientsToCampaign(
    campaignUuid: 'e184d08f-5081-4fbb-9604-7a2460c3fb3a',
    recipients: [
        new CampaignRecipient(
            uuid: '7f3eb754-709e-4837-b82a-49c83223eed6',
            name: 'John Doe',
            reachableAt: 'john@doe.tld',
        ),
    ],
);

// Start sending: each recipient is dispatched as a job onto the batch.
$manager->start(campaignUuid: 'e184d08f-5081-4fbb-9604-7a2460c3fb3a');

// Inspect progress.
$campaign = $manager->find(campaignUuid: 'e184d08f-5081-4fbb-9604-7a2460c3fb3a');

$campaign->progress;               // RoundlyConsulting\Campaigns\CampaignProgress
$campaign->progress->status;       // RoundlyConsulting\Campaigns\Enums\CampaignStatus
$campaign->progress->percentage(); // float, e.g. 42.5
$campaign->startedAt;              // Carbon instance or null
$campaign->endedAt;               // Carbon instance or null

// Cancel a running campaign and its batch.
$manager->cancel(campaignUuid: 'e184d08f-5081-4fbb-9604-7a2460c3fb3a');
```

### Value objects

- `Campaign` — `uuid`, `subject`, `content`, `fromName`, `fromAddress`, `progress`,
  `startedAt`, `endedAt`, `batch`.
- `CampaignProgress` — `status`, `sent`, `pending`, `total`, and `percentage(): float`.
- `CampaignRecipient` — `uuid`, `name`, `reachableAt`, `hasBeenProcessed`, `errorOccured`,
  `errorMessage`.
- `Enums\CampaignStatus` — `Created`, `Pending`, `Processing`, `Completed`, `Failed`,
  `Canceled`.

### Custom manager

`InMemoryManager` keeps campaigns and recipients in static arrays — ideal for tests and
create-and-send flows. For persisted campaigns (delayed starts, dashboards, retries),
implement `RoundlyConsulting\Campaigns\Managers\Manager` against your own storage and point
the `campaigns.manager` config at it. Your implementation must build a job batch to process
each recipient through the queue.

### Custom processing job

By default each recipient is processed by `Jobs\SendCampaignEmail`, which sends the campaign
content as an email. Set `campaigns.process-recipient-job` to your own job to deliver via SMS,
push, or `Notification` instead. The job receives the `Campaign` and `CampaignRecipient` and
should call `markRecipientAsProcessed()` / `markRecipientAsFailed()` on the manager.

### Console command

List campaigns and their progress:

```bash
php artisan campaigns:list --offset=0 --limit=10
```

| Option | Default | Description |
|---|---|---|
| `--offset` | `0` | Number of campaigns to skip. |
| `--limit` | `10` | Maximum number of campaigns to display. |

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG](CHANGELOG.md).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
