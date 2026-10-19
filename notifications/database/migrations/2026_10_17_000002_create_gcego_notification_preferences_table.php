<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Users' notification choices: a channel on or off per category ("*": all)
 * and tenant ("": all).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gcego_notification_preferences', static function (Blueprint $table): void {
            $table->id();
            $table->string('notifiable_type');
            $table->string('notifiable_id', 64);
            $table->string('tenant', 100)->default('');
            $table->string('category', 48)->default('*');
            $table->string('channel', 20);
            $table->boolean('enabled');
            $table->timestamps();

            $table->unique(['notifiable_type', 'notifiable_id', 'tenant', 'category', 'channel'], 'gcego_notification_preferences_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gcego_notification_preferences');
    }
};
