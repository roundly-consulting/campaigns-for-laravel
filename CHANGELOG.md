# Changelog

All notable changes to `campaigns-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

Initial public release.

### Added

- Send a campaign to many recipients on Laravel queues and job batches with the fluent
  `Campaigns::create()->from()->to()->dispatch()` builder.
- Live progress (`percentage()`, `remaining()`, `isComplete()`) and `Campaigns::cancel()`.
- Swappable per-recipient delivery job: `SendCampaignEmail` by default, `SendCampaignNotification`
  for any Laravel notification channel, or your own job for SMS or push.
- Storage-agnostic managers: in-memory out of the box, or an opt-in database-backed manager.
- `CampaignStatus` enum and `Campaign`, `CampaignProgress` and `CampaignRecipient` value objects.
- Events for campaign prepare, start and completion, and per-recipient success or failure.
- `campaigns:list` and `campaigns:cancel` Artisan commands.
- Recipient resolution from contact records or any contact owner, with `onlyVerified()` and
  `viaContactType()`, built on contacts-for-laravel.
- Typed, database-backed send defaults, built on options-for-laravel.
