<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

/**
 * A recipient may stand for a host model (`recipient_type` / `recipient_id`, indexed together)
 * instead of — or as well as — an address, so `reachable_at` becomes nullable. Additive: every
 * existing row keeps its address and gets no model.
 */
return new class extends Migration
{
    public function up(): void
    {
        $keyType = KeyType::fromConfig('campaigns.recipients.key-type');

        Schema::table('campaign_recipients', function (Blueprint $table) use ($keyType): void {
            $table->morphKey('recipient', $keyType, nullable: true);
            $table->json('meta')->nullable();
            $table->string('reachable_at')->nullable()->change();
        });
    }
};
