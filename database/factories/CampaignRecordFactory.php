<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;
use RoundlyConsulting\Campaigns\Models\CampaignRecord;

/** @extends Factory<CampaignRecord> */
final class CampaignRecordFactory extends Factory
{
    protected $model = CampaignRecord::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'subject' => $this->faker->sentence(),
            'content' => $this->faker->paragraph(),
            'from_name' => $this->faker->company(),
            'from_address' => $this->faker->safeEmail(),
            'status' => CampaignStatus::Created,
            'sent' => 0,
            'failed' => 0,
            'pending' => 0,
            'total' => 0,
            'batch' => null,
            'started_at' => null,
            'ended_at' => null,
        ];
    }
}
