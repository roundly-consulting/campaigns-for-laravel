<?php

declare(strict_types=1);

/**
 * A — the secret-safe `about` capture.
 *
 * Purchases #13 is the bug this exists for: the fleet's most credential-heavy `about`
 * section was guarded by negative assertions against `app(Kernel::class)->output()`, which
 * returns `''`, so every "does not leak" check was vacuous. Campaigns had NO about test at
 * all, so this is new coverage rather than a replacement.
 *
 * What campaigns must never render is its deployment identity: the sending domain it mails
 * from and the host's queue topology. A bulk-mail sender address is reputational — publish
 * it in `about` output and it is in every screenshot and support ticket — which is why the
 * provider reports presence and never the value.
 *
 * `mustRender` is asserted BEFORE any secret check runs and throws at call time if empty, so
 * this cannot silently degrade into the purchases shape.
 */
it('renders the campaigns section without leaking the sender identity or queues', function (): void {
    config()->set('campaigns.from-name', 'Acme Internal Comms');
    config()->set('campaigns.from-address', 'noreply@acme-internal.example');
    config()->set('campaigns.batch-queue', 'acme-batch-priority');
    config()->set('campaigns.sending-queue', 'acme-sending-bulk');
    config()->set('campaigns.notification', 'App\\Notifications\\SecretInternalBlast');
    config()->set('campaigns.recipients.only-verified', true);

    expect('campaigns')->toLeakNoSecrets(
        secrets: [
            // The sending identity — reputational, and a spoofing target.
            'Acme Internal Comms',
            'noreply@acme-internal.example',
            'acme-internal.example',
            // The host's queue topology is its infrastructure, not campaigns' business.
            'acme-batch-priority',
            'acme-sending-bulk',
        ],
        mustRender: [
            // The positive proof each line reports rather than being silently empty.
            'Store',
            'Recipient job',
            'From name',
            'SET',
            'Verified recipients only',
            'ON',
        ],
    );
});

/**
 * The switches render as switches, and an unset sender reports MISSING rather than an empty
 * string. Kept separate: it is a rendering pin, not a leak pin, and folding it into the case
 * above would need the opposite config.
 */
it('reports the configured store and switches in the about section', function (): void {
    config()->set('campaigns.from-name', '');
    config()->set('campaigns.from-address', '');
    config()->set('campaigns.notification', null);
    config()->set('campaigns.recipients.only-verified', false);

    expect('campaigns')->toLeakNoSecrets(
        secrets: ['noreply@acme-internal.example'],
        mustRender: ['InMemoryCampaignStore', 'SendCampaignEmail', 'NONE', 'DEFAULT', 'OFF'],
    );
});
