<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RoundlyConsulting\Campaigns\Managers\Manager;
use RoundlyConsulting\Campaigns\Support\CampaignSettings;
use RoundlyConsulting\Campaigns\Support\RecipientResolver;
use RoundlyConsulting\Contacts\Concerns\HasContacts;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Models\Contact;

final class PendingCampaign
{
    private string $uuid;

    private ?string $fromName = null;

    private ?string $fromAddress = null;

    /** Explicit verified-only toggle; null defers to the configured default. */
    private ?bool $onlyVerified = null;

    /** Explicit contact kind; null defers to the configured default. */
    private ?ContactType $contactType = null;

    /** @var list<CampaignRecipient> */
    private array $recipients = [];

    public function __construct(
        private readonly Manager $manager,
        private string $subject,
        private string $content,
    ) {
        $this->uuid = (string) Str::uuid();
    }

    public function uuid(string $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function subject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function content(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function from(string $address, ?string $name = null): self
    {
        $this->fromAddress = $address;
        $this->fromName = $name ?? $address;

        return $this;
    }

    /**
     * Only resolve verified contacts when adding HasContacts owners / contacts.
     */
    public function onlyVerified(bool $onlyVerified = true): self
    {
        $this->onlyVerified = $onlyVerified;

        return $this;
    }

    /**
     * Which contact kind an owner's recipient is resolved from (email, phone…).
     */
    public function viaContactType(ContactType|string $type): self
    {
        $this->contactType = $type instanceof ContactType
            ? $type
            : ContactType::from($type);

        return $this;
    }

    /**
     * Add one or more recipients. Accepts an email/route string, a
     * CampaignRecipient, a contacts-for-laravel Contact record, a HasContacts
     * owner model, or an iterable of any of these. Calls are additive; owners
     * or contacts with no matching (or no verified) contact are skipped.
     *
     * @param  string|CampaignRecipient|Model|iterable<mixed>  $recipients
     */
    public function to(string|CampaignRecipient|Model|iterable $recipients): self
    {
        if (is_string($recipients) || $recipients instanceof CampaignRecipient || $recipients instanceof Model) {
            $this->addRecipient($recipients);

            return $this;
        }

        foreach ($recipients as $recipient) {
            $this->addRecipient($recipient);
        }

        return $this;
    }

    /**
     * Prepare the campaign (create the batch, set it to Pending) without sending.
     */
    public function prepare(): Campaign
    {
        $campaign = $this->buildCampaign();

        $this->manager->prepare($campaign);

        if ($this->recipients !== []) {
            $this->manager->pushRecipientsToCampaign($this->uuid, $this->recipients);
        }

        return $this->manager->findOrFail($this->uuid);
    }

    /**
     * Prepare and start the campaign in one call.
     */
    public function dispatch(): Campaign
    {
        $this->prepare();

        $this->manager->start($this->uuid);

        return $this->manager->findOrFail($this->uuid);
    }

    private function buildCampaign(): Campaign
    {
        $settings = $this->settings();

        $fromAddress = $this->fromAddress ?? $settings->fromAddress();
        $fromName = $this->fromName ?? $settings->fromName();

        return new Campaign(
            uuid: $this->uuid,
            subject: $this->subject,
            content: $this->content,
            fromName: $fromName,
            fromAddress: $fromAddress,
        );
    }

    private function addRecipient(mixed $recipient): void
    {
        $resolved = $this->resolveRecipient($recipient);

        if ($resolved instanceof CampaignRecipient) {
            $this->recipients[] = $resolved;
        }
    }

    private function resolveRecipient(mixed $recipient): ?CampaignRecipient
    {
        if ($recipient instanceof CampaignRecipient) {
            return $recipient;
        }

        if (is_string($recipient)) {
            return new CampaignRecipient(
                uuid: (string) Str::uuid(),
                name: $recipient,
                reachableAt: $recipient,
            );
        }

        if ($recipient instanceof Contact) {
            return $this->resolver()->fromContact($recipient, $this->effectiveOnlyVerified());
        }

        if ($recipient instanceof Model && $this->usesContacts($recipient)) {
            return $this->resolver()->fromOwner(
                $recipient,
                $this->effectiveContactType(),
                $this->effectiveOnlyVerified(),
            );
        }

        throw new \InvalidArgumentException(
            'Recipients must be a string, a CampaignRecipient, a Contact, or a HasContacts owner model.'
        );
    }

    private function usesContacts(Model $model): bool
    {
        return in_array(HasContacts::class, class_uses_recursive($model), true);
    }

    private function effectiveOnlyVerified(): bool
    {
        return $this->onlyVerified ?? $this->settings()->onlyVerifiedRecipients();
    }

    private function effectiveContactType(): ContactType
    {
        return $this->contactType ?? $this->settings()->defaultRecipientContactType();
    }

    private function resolver(): RecipientResolver
    {
        return app(RecipientResolver::class);
    }

    private function settings(): CampaignSettings
    {
        return app(CampaignSettings::class);
    }
}
