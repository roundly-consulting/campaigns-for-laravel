<?php

declare(strict_types=1);

use RoundlyConsulting\Campaigns\Exceptions\CampaignException;
use RoundlyConsulting\Campaigns\Notifications\CampaignNotification;
use RoundlyConsulting\Testing\Arch\ArchPresets;

/**
 * Campaigns shipped **no arch test at all** — this whole file is new coverage, which is the
 * jwt shape (its bug #4, `final` on a swappable model, existed precisely because nothing was
 * looking).
 */
ArchPresets::strictTypes('RoundlyConsulting\Campaigns');

/**
 * Two exemptions, each a real extension point rather than an oversight:
 *
 *  - CampaignException, the exception base hosts catch;
 *  - CampaignNotification, which hosts EXTEND to write the notification
 *    `campaigns.notification` names — the documented seam of the whole notification path.
 *
 * No `swappableModelsAreNotFinal` counterweight, and that is the correct outcome rather
 * than an omission. `CampaignRecord` and `CampaignRecipientRecord` are `final`, and are
 * allowed to be: `config/campaigns.php` ships no model key inviting a host to swap either.
 * The fleet's 7× fatal is `final` on a model the config DOES advertise a swap of; a final
 * model with no advertised seam is just a closed class.
 *
 * The row spec's `Swap? 0` was verified against the config file rather than assumed, and it
 * holds — but note what it is NOT: `campaigns.manager` binds a Manager implementation,
 * `campaigns.process-recipient-job` a job class, and `campaigns.notification` a
 * notification. All three are class-string bindings, and none is an Eloquent model, so `S`
 * genuinely does not apply here (the same distinction that re-scored metrics' "4" and
 * kubernetes-api's "11").
 */
ArchPresets::finalByDefault('RoundlyConsulting\Campaigns', [
    CampaignException::class,
    CampaignNotification::class,
]);

/**
 * Campaigns does no cryptography. The ban is a standing guard against an unsubscribe token
 * or a recipient signature being hand-rolled here rather than in crypto-for-laravel — a
 * realistic temptation for a bulk-mail package.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\Campaigns');

/**
 * `modelsResolveThroughSeam` is REJECTED, with cause: campaigns ships two Eloquent models
 * behind NO config key, so the stray-literal half has no swap key to look for and the
 * late-static-binding half no configured class to protect. Both halves are structurally
 * inert. This is the settled `Swap? == 0` pre-classification, not a per-row re-litigation.
 */

/**
 * The Dependency Policy as a test. No `alsoAllow`: campaigns' `require` ships only
 * php/illuminate/roundly, and the workflow installs test tooling with `--dev`. If it goes
 * red the graph is wrong — never widen the allow-list to quiet it.
 */
ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../composer.json');

ArchPresets::noDebuggingLeftovers();
