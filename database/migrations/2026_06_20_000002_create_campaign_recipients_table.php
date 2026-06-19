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
            $table->uuid('uuid')->unique();
            $table->uuid('campaign_uuid')->index();
            $table->string('name');
            $table->string('reachable_at');
            $table->boolean('has_been_processed')->default(false);
            $table->boolean('error_occured')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
