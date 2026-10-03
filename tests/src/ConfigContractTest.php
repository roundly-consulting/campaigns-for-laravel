<?php

declare(strict_types=1);

/**
 * C — the config-key contract, pinned in both directions.
 *
 * Reads are scraped from source **tokens**, never a regex — media #27's near-miss was a
 * regex over raw text satisfied by a *docblock mention* of the key, which stayed green with
 * the fix reverted. A docblock is a comment token here, never a read.
 *
 *  - forward — every key the code reads is shipped (shops #18: a whole feature reading
 *    `shops.payments.*` while the file shipped `payment.*`, green because the suite set the
 *    same wrong key the code read);
 *  - reverse — every shipped leaf is read (alerts #24; media #27's `max_file_size` cap that
 *    never applied). Campaigns' config is almost entirely defaults that seed Options, which
 *    is exactly the shape where a key quietly stops being read.
 */
it('ships exactly the config keys it reads', function (): void {
    expect(__DIR__.'/../../config/campaigns.php')->toSatisfyConfigContract(__DIR__.'/../../src', [
        // These are read through the strict readers in Support\CampaignsConfig, which take the
        // key as an argument (`self::string($key, …)`, `self::implementation($key, …)`) or go
        // through `Config::enum(…)` — so the scraper sees a `config(` token with a VARIABLE key,
        // or no `config(` token at all, and the literal key one frame up. Naming each key is
        // what connects the two.
        //
        // `campaigns.store` is the load-bearing one: it is read at register() to bind the
        // CampaignStore implementation, and CampaignsConfig is its ONLY reader.
        //
        // The keys are named exactly rather than using a blanket `'campaigns.'`, which would
        // count ANY string literal under the prefix as a read wherever it appeared —
        // including queue names and translation keys that are not config keys at all (the
        // trap alerts hit with its `alerts.health` route-name default).
        'extraReadPrefixes' => [
            'campaigns.store',
            'campaigns.process-recipient-job',
            'campaigns.notification-channel',
            'campaigns.recipients.contact-type',
            'campaigns.sending-queue',
            'campaigns.from-name',
            'campaigns.from-address',
        ],

        // Deliberately NO `excludeFromReverse` for the provider. The testing README's own
        // example excludes the service provider on the grounds that "a render is not a
        // read" — but CampaignsServiceProvider's `contributesToAbout()` closure reads ten
        // `campaigns.*` keys for real, register() resolves `campaigns.store` to bind the
        // CampaignStore, and the toolkit's `bindFromConfig()` reads more still. Excluding it would
        // discard the only reader of most of this file.
    ]);
});
