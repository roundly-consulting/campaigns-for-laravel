<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class CampaignRecipient
{
    /**
     * `campaignUuid` is filled in when the recipient is added to a campaign; a recipient
     * you build yourself starts without one.
     */
    public function __construct(
        public string $uuid,
        public string $name,
        public string $reachableAt,
        public bool $hasBeenProcessed = false,
        public bool $errorOccured = false,
        public ?string $errorMessage = null,
        public ?string $campaignUuid = null,
    ) {}

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
            );
        }

        $copy = clone $this;
        $copy->campaignUuid = $campaignUuid;

        return $copy;
    }

    /**
     * @return array{uuid: string, name: string, reachableAt: string, hasBeenProcessed: bool, errorOccured: bool, errorMessage: string|null, campaignUuid: string|null}
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
        ];
    }
}
