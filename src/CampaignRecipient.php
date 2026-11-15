<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class CampaignRecipient
{
    /**
     * The morph type (`getMorphClass()`) of the model this recipient stands for, or null for an
     * address-only recipient.
     *
     * Plain properties rather than promoted ones, so a job queued before these existed still
     * unserialises with them null instead of uninitialised.
     */
    public ?string $recipientType = null;

    /** The key of the model this recipient stands for, or null for an address-only recipient. */
    public int|string|null $recipientId = null;

    /** @var array<string, mixed>|null host data carried with the recipient, never read by the package */
    public ?array $meta = null;

    /**
     * `campaignUuid` is filled in when the recipient is added to a campaign; a recipient
     * you build yourself starts without one.
     *
     * A recipient is an address (`reachableAt`), a model (`recipientType` + `recipientId`), or
     * both. Without a model, `reachableAt` is required.
     *
     * @param  array<string, mixed>|null  $meta
     *
     * @throws InvalidArgumentException when it names no address and no model, or half a model
     */
    public function __construct(
        public string $uuid,
        public string $name,
        public ?string $reachableAt = null,
        public bool $hasBeenProcessed = false,
        public bool $errorOccured = false,
        public ?string $errorMessage = null,
        public ?string $campaignUuid = null,
        ?string $recipientType = null,
        int|string|null $recipientId = null,
        ?array $meta = null,
    ) {
        if (($recipientType === null || $recipientType === '') !== ($recipientId === null || $recipientId === '')) {
            throw new InvalidArgumentException('A model recipient needs both a recipientType and a recipientId.');
        }

        if ($recipientType === null && $reachableAt === null) {
            throw new InvalidArgumentException('A recipient needs a reachableAt address unless it stands for a model.');
        }

        $this->recipientType = $recipientType === '' ? null : $recipientType;
        $this->recipientId = $recipientId === '' ? null : $recipientId;
        $this->meta = $meta;
    }

    /**
     * A recipient that stands for a model — a User, a Member — keyed by its morph type and key.
     * The address is optional: a job that delivers to the model (a notification, an in-app
     * message) resolves it with model() and needs none.
     *
     * @param  array<string, mixed>|null  $meta
     *
     * @throws InvalidArgumentException when the model has not been saved
     */
    public static function forModel(Model $model, string $name, ?string $reachableAt = null, ?array $meta = null): self
    {
        $key = $model->getKey();

        if (! is_int($key) && ! is_string($key)) {
            throw new InvalidArgumentException('A model recipient needs a saved model with an int or string key.');
        }

        return new self(
            uuid: (string) Str::uuid(),
            name: $name,
            reachableAt: $reachableAt,
            recipientType: $model->getMorphClass(),
            recipientId: $key,
            meta: $meta,
        );
    }

    /**
     * Scope a list of recipients to one campaign (see forCampaign()).
     *
     * @internal used by PrepareCampaignAction and CampaignsFake
     *
     * @param  iterable<mixed>  $recipients
     * @return list<self>
     *
     * @throws InvalidArgumentException when an item is not a CampaignRecipient
     */
    public static function scopeAll(iterable $recipients, string $campaignUuid): array
    {
        $scoped = [];

        foreach ($recipients as $recipient) {
            if (! $recipient instanceof self) {
                throw new InvalidArgumentException('Every recipient must be a CampaignRecipient.');
            }

            $scoped[] = $recipient->forCampaign($campaignUuid);
        }

        return $scoped;
    }

    public function belongsTo(string $campaignUuid): bool
    {
        return $this->campaignUuid === $campaignUuid;
    }

    /**
     * Whether this recipient stands for a model (see forModel()).
     *
     * @phpstan-assert-if-true !null $this->recipientType
     * @phpstan-assert-if-true !null $this->recipientId
     */
    public function hasModel(): bool
    {
        return $this->recipientType !== null && $this->recipientId !== null;
    }

    /**
     * Whether this recipient stands for the given model. Keys compare as strings, so an int key
     * read back from a string column still matches.
     */
    public function isFor(Model $model): bool
    {
        $key = $model->getKey();

        return $this->hasModel()
            && $this->recipientType === $model->getMorphClass()
            && (is_int($key) || is_string($key))
            && (string) $this->recipientId === (string) $key;
    }

    /**
     * The model this recipient stands for, looked up fresh (morph map aliases resolve), or null
     * for an address-only recipient and for a model that no longer exists.
     */
    public function model(): ?Model
    {
        if (! $this->hasModel()) {
            return null;
        }

        $class = Relation::getMorphedModel($this->recipientType) ?? $this->recipientType;

        if (! is_subclass_of($class, Model::class)) {
            return null;
        }

        $model = (new $class)->newQuery()->find($this->recipientId);

        return $model instanceof Model ? $model : null;
    }

    /**
     * A copy of this recipient scoped to the campaign. An unscoped recipient (or one
     * already in this campaign) keeps its uuid; a recipient of ANOTHER campaign becomes a
     * fresh one — new uuid, clean delivery state — so adding it never moves the original
     * out of the campaign it belongs to.
     */
    public function forCampaign(string $campaignUuid): self
    {
        if ($this->campaignUuid !== null && $this->campaignUuid !== $campaignUuid) {
            return new self(
                uuid: (string) Str::uuid(),
                name: $this->name,
                reachableAt: $this->reachableAt,
                campaignUuid: $campaignUuid,
                recipientType: $this->recipientType,
                recipientId: $this->recipientId,
                meta: $this->meta,
            );
        }

        $copy = clone $this;
        $copy->campaignUuid = $campaignUuid;

        return $copy;
    }

    /**
     * @return array{uuid: string, name: string, reachableAt: string|null, hasBeenProcessed: bool, errorOccured: bool, errorMessage: string|null, campaignUuid: string|null, recipientType: string|null, recipientId: int|string|null, meta: array<string, mixed>|null}
     */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'reachableAt' => $this->reachableAt,
            'hasBeenProcessed' => $this->hasBeenProcessed,
            'errorOccured' => $this->errorOccured,
            'errorMessage' => $this->errorMessage,
            'campaignUuid' => $this->campaignUuid,
            'recipientType' => $this->recipientType,
            'recipientId' => $this->recipientId,
            'meta' => $this->meta,
        ];
    }
}
