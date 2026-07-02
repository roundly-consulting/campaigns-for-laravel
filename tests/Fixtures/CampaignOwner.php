<?php

declare(strict_types=1);

namespace RoundlyConsulting\Campaigns\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Contacts\Concerns\HasContacts;

/**
 * A minimal host model that owns contacts, used to exercise recipient
 * resolution from a HasContacts owner.
 *
 * @property int $id
 * @property string|null $name
 */
final class CampaignOwner extends Model
{
    use HasContacts;

    protected $table = 'campaign_users';

    protected $guarded = [];
}
