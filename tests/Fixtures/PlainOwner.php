<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A host model that does NOT own contacts, used to assert ->to() rejects
 * unsupported models.
 *
 * @property int $id
 */
final class PlainOwner extends Model
{
    protected $table = 'campaign_users';

    protected $guarded = [];
}
