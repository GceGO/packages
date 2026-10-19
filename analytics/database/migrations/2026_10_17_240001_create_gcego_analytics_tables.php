<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Report exports (private files behind authorized, signed, expiring
 * downloads) and scheduled reports. Owner and recipient keys are strings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gcego_analytics_schedules', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('report', 64);
            $table->string('owner_type');
            $table->string('owner_id', 64);
            $table->string('tenant', 100)->nullable();
            $table->json('filters')->nullable();
            $table->string('range', 32)->nullable();
            $table->string('format', 8);
            $table->string('frequency', 16);
            $table->unsignedTinyInteger('day')->nullable();
            $table->string('time', 5);
            $table->string('timezone', 64);
            $table->json('recipients');
            $table->boolean('active')->default(true);
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status', 32)->nullable();
            $table->unsignedSmallInteger('failures')->default(0);
            $table->timestamps();

            $table->index(['active', 'next_run_at']);
            $table->index(['owner_type', 'owner_id']);
        });

        Schema::create('gcego_analytics_exports', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('report', 64);
            $table->string('format', 8);
            $table->string('status', 16);
            $table->string('owner_type');
            $table->string('owner_id', 64);
            $table->string('tenant', 100)->nullable();
            $table->json('request');
            $table->json('recipients')->nullable();
            $table->foreignId('schedule_id')->nullable()->constrained('gcego_analytics_schedules')->nullOnDelete();
            $table->unsignedInteger('rows')->nullable();
            $table->string('disk', 64);
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('error', 120)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['owner_type', 'owner_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gcego_analytics_exports');
        Schema::dropIfExists('gcego_analytics_schedules');
    }
};
