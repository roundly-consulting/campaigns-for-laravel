<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
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
        ];
    }
}
