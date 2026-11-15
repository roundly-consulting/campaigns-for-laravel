<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Campaigns\Database\Factories\CampaignRecipientRecordFactory;

/**
 * @property int $id
 * @property string $uuid
 * @property string $campaign_uuid
 * @property string $name
 * @property string|null $reachable_at
 * @property string|null $recipient_type
 * @property int|string|null $recipient_id
 * @property array<string, mixed>|null $meta
 * @property bool $has_been_processed
 * @property bool $error_occured
 * @property string|null $error_message
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 */
final class CampaignRecipientRecord extends Model
{
    /** @use HasFactory<CampaignRecipientRecordFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'campaign_recipients';

    protected $guarded = [];

    /**
     * @return BelongsTo<CampaignRecord, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CampaignRecord::class, 'campaign_uuid', 'uuid');
    }

    /**
     * The host model this recipient stands for; null for an address-only recipient.
     *
     * @return MorphTo<Model, $this>
     */
    public function recipient(): MorphTo
    {
        return $this->morphTo('recipient', 'recipient_type', 'recipient_id');
    }

    protected static function newFactory(): CampaignRecipientRecordFactory
    {
        return CampaignRecipientRecordFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_been_processed' => 'boolean',
            'error_occured' => 'boolean',
            'meta' => 'array',
        ];
    }
}
