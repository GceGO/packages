<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery outcomes per notification and channel (only written when
 * gcego-notifications.tracking.enabled). No notification content is kept here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gcego_notification_deliveries', static function (Blueprint $table): void {
            $table->id();
            $table->uuid('notification_id');
            $table->string('recipient', 120)->nullable();
            $table->string('channel', 20);
            $table->string('status', 16);
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['notification_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gcego_notification_deliveries');
    }
};
