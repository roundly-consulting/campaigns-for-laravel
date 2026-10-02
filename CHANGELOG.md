# Changelog

All notable changes to `campaigns-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

Initial public release.

### Added

- Send a campaign to many recipients on Laravel queues and job batches with the fluent
  `Campaigns::create()->from()->to()->dispatch()` builder.
- Live progress (`sent`, `failed`, `percentage()`, `remaining()`, `isComplete()`) and
  `Campaigns::cancel()`.
- Swappable per-recipient delivery job: `SendCampaignEmail` by default, `SendCampaignNotification`
  for any Laravel notification channel, or your own job for SMS or push.
- Storage-agnostic stores: in-memory out of the box, or an opt-in database-backed store.
- `CampaignStatus` enum and `Campaign`, `CampaignProgress` and `CampaignRecipient` value objects.
- Events for campaign prepare, start and completion, and per-recipient success or failure.
- `campaigns:list` and `campaigns:cancel` Artisan commands.
- Recipient resolution from contact records or any contact owner, with `onlyVerified()` and
  `viaContactType()`, built on contacts-for-laravel.
- Typed, database-backed send defaults, built on options-for-laravel.
- `Campaigns::start()`, `Campaigns::prepare(Campaign, $recipients)`, `Campaigns::all()` and
  `Campaigns::settings()`.
- `Campaigns::campaign($uuid)` handle: `progress()`, `recipients()`, `recipient()`, `batch()`,
  `start()`, `cancel()`, `markProcessed()`, `markFailed()` — scoped to its campaign.
- Actions for every write: `PrepareCampaignAction`, `StartCampaignAction`,
  `CancelCampaignAction`, `MarkRecipientProcessedAction`, `MarkRecipientFailedAction`.
- `Campaigns::fake()` with `assertCreated`, `assertDispatched`, `assertStarted`,
  `assertCancelled`, `assertRecipientProcessed`, `assertRecipientFailed` and an
  `assertNothing*` for each.
- A recipients listing on the storage contract, and `CampaignRecipient::$campaignUuid`.
- `CampaignProgress::$failed`, and `insert()`, `saveIfStatus()` and `countRecipients()` on the
  storage contract.
- `CampaignAlreadyExists`, thrown when a campaign is prepared under a uuid that is taken.

### Changed

- The storage contract `Managers\Manager` is now `Contracts\CampaignStore` (persistence only);
  `InMemoryManager` / `DatabaseManager` are `Stores\InMemoryCampaignStore` /
  `Stores\DatabaseCampaignStore`, and the config key `campaigns.manager` is `campaigns.store`
  (env `CAMPAIGNS_STORE`).
- `Campaigns::each()` is replaced by `Campaigns::all()`, and `Campaigns::manager()` is removed.
- `Campaigns::cancel()` returns the campaign.
- Delivery jobs receive the `CampaignManager`:
  `ProcessesCampaignRecipient::handle(CampaignManager $campaigns)`, recording outcomes with
  `$campaigns->campaign($campaign)->markProcessed()` / `->markFailed()`.
- `CampaignPrepared` fires once the recipients are stored.
- `campaigns:cancel` warns instead of reporting success for a campaign that already ended.
- Per-recipient jobs run on `sending-queue`; the `batch-queue` config key, the
  `DefaultBatchQueue` option and `CampaignSettings::batchQueue()` are removed (a batch pushes
  all of its jobs onto one queue, so it had nothing of its own to apply to).
- A campaign ends `Failed` only when not one delivery got through; otherwise it completes.
- The in-memory store lives for one request or queued job (bound scoped).

### Fixed

- A cancelled campaign no longer turns `Completed` when its batch finishes.
- Starting a campaign that is already sending no longer queues every recipient again.
- Progress now reports the recipient total while a campaign is sending (it read 0 until the
  batch finished).
- Adding another campaign's recipient no longer moves it out of that campaign on the
  database store.
- A recipient of one campaign can no longer be marked processed or failed through another.
- A campaign without a sender no longer fails every email: it sends from the mailer's
  global `mail.from`.
- Two processes starting the same campaign at once no longer send it twice.
- `sending-queue` (and the `DefaultSendingQueue` option) now decides the queue the delivery
  jobs run on; they used to land on the batch queue.
- One failing delivery job no longer ends the campaign as `Failed` while the rest keep
  sending, and the campaign can still be cancelled.
- A campaign with no recipients (or every owner filtered out) completes instead of staying
  `Processing` forever.
- Failed deliveries are no longer counted as sent.
- Preparing a campaign under an existing uuid no longer overwrites and re-sends it.
- The database store keeps a recipient added to two campaigns in both, like the in-memory
  store.
- `->to()` no longer adds the same address twice.
- The in-memory store no longer grows (and leaks campaigns between jobs) for a queue
  worker's lifetime.
