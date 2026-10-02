<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_recipients', function (Blueprint $table): void {
            $table->id();
            // A recipient uuid is unique within its campaign: the same recipient may be
            // added to several campaigns, and each keeps its own row and delivery state.
            $table->uuid('uuid');
            $table->uuid('campaign_uuid')->index();
            $table->string('name');
            $table->string('reachable_at');
            $table->boolean('has_been_processed')->default(false);
            $table->boolean('error_occured')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['campaign_uuid', 'uuid']);
        });
    }
};
