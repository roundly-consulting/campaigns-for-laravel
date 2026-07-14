<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RoundlyConsulting\Campaigns\CampaignRecipient;
use RoundlyConsulting\Contacts\Enums\ContactType;
use RoundlyConsulting\Contacts\Models\Contact;
use RoundlyConsulting\Contacts\Support\ContactModel;

/**
 * Maps contacts-for-laravel records into campaign recipients. Read-only: it
 * never writes contacts, it only resolves a reachable address + display name.
 */
final class RecipientResolver
{
    /**
     * Resolve a single Contact record. Returns null when the contact holds no
     * reachable value, or when verified-only is required and it is unverified.
     */
    public function fromContact(Contact $contact, bool $verifiedOnly = false): ?CampaignRecipient
    {
        if ($verifiedOnly && ! $contact->isVerified()) {
            return null;
        }

        $value = $contact->value;

        if (! is_string($value) || $value === '') {
            return null;
        }

        return new CampaignRecipient(
            uuid: (string) Str::uuid(),
            name: $this->nameFor($contact, $value),
            reachableAt: $value,
        );
    }

    /**
     * Resolve a HasContacts owner's contact of the given kind (primary first,
     * then the first ordered contact), honouring the verified-only flag.
     * Returns null when the owner has no matching contact.
     */
    public function fromOwner(Model $owner, ContactType $type, bool $verifiedOnly = false): ?CampaignRecipient
    {
        $contact = $this->resolveOwnerContact($owner, $type, $verifiedOnly);

        return $contact instanceof Contact
            ? $this->fromContact($contact, $verifiedOnly)
            : null;
    }

    private function resolveOwnerContact(Model $owner, ContactType $type, bool $verifiedOnly): ?Contact
    {
        // The contacts package owns `contacts.model` — resolve through its own
        // resolver rather than re-reading (and re-validating) the key here.
        $model = ContactModel::class();

        $base = $model::query()->forOwner($owner)->ofType($type);

        if ($verifiedOnly) {
            $base->verified();
        }

        $primary = (clone $base)->primary()->ordered()->first();

        return $primary ?? $base->ordered()->first();
    }

    private function nameFor(Contact $contact, string $fallback): string
    {
        $owner = $contact->owner;

        if ($owner instanceof Model) {
            $ownerName = $owner->getAttribute('name');

            if (is_string($ownerName) && $ownerName !== '') {
                return $ownerName;
            }
        }

        if ($contact->name !== '') {
            return $contact->name;
        }

        if (is_string($contact->label) && $contact->label !== '') {
            return $contact->label;
        }

        return $fallback;
    }
}
