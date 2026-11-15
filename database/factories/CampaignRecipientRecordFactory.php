<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RoundlyConsulting\Campaigns\Models\CampaignRecipientRecord;

/** @extends Factory<CampaignRecipientRecord> */
final class CampaignRecipientRecordFactory extends Factory
{
    protected $model = CampaignRecipientRecord::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'campaign_uuid' => (string) Str::uuid(),
            'name' => $this->faker->name(),
            'reachable_at' => $this->faker->safeEmail(),
            'has_been_processed' => false,
            'error_occured' => false,
            'error_message' => null,
            'recipient_type' => null,
            'recipient_id' => null,
            'meta' => null,
        ];
    }

    /**
     * A recipient that stands for the given model (see CampaignRecipient::forModel()).
     */
    public function forModel(Model $model, bool $withAddress = false): self
    {
        return $this->state(fn (): array => [
            'recipient_type' => $model->getMorphClass(),
            'recipient_id' => $model->getKey(),
            'reachable_at' => $withAddress ? $this->faker->safeEmail() : null,
        ]);
    }
}
