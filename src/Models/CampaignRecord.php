<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Campaigns\Database\Factories\CampaignRecordFactory;
use RoundlyConsulting\Campaigns\Enums\CampaignStatus;

/**
 * @property int $id
 * @property string $uuid
 * @property string $subject
 * @property string $content
 * @property string $from_name
 * @property string $from_address
 * @property CampaignStatus $status
 * @property int $sent
 * @property int $pending
 * @property int $total
 * @property string|null $batch
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $ended_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 */
final class CampaignRecord extends Model
{
    /** @use HasFactory<CampaignRecordFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'campaigns';

    protected $guarded = [];

    /**
     * @return HasMany<CampaignRecipientRecord, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipientRecord::class, 'campaign_uuid', 'uuid');
    }

    protected static function newFactory(): CampaignRecordFactory
    {
        return CampaignRecordFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'sent' => 'integer',
            'pending' => 'integer',
            'total' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
