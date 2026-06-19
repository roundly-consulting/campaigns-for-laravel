<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns;

final class CampaignRecipient
{
    public function __construct(
        public string $uuid,
        public string $name,
        public string $reachableAt,
        public bool $hasBeenProcessed = false,
        public bool $errorOccured = false,
        public ?string $errorMessage = null,
    ) {}

    /**
     * @return array{uuid: string, name: string, reachableAt: string, hasBeenProcessed: bool, errorOccured: bool, errorMessage: string|null}
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
        ];
    }
}
