<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('subject');
            $table->longText('content');
            $table->string('from_name');
            $table->string('from_address');
            $table->string('status')->default('Created')->index();
            $table->unsignedBigInteger('sent')->default(0);
            $table->unsignedBigInteger('failed')->default(0);
            $table->unsignedBigInteger('pending')->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->string('batch')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
