<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * gcego/notifications stores notifications in Laravel's standard
 * "notifications" table (created here only when the application has none)
 * and adds nullable columns, so inbox queries filter on indexed columns:
 * tenant, panel, category, idempotency key and expiry. Existing rows and
 * columns are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', static function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('notifications', static function (Blueprint $table): void {
            foreach (['gcego_tenant' => 100, 'gcego_panel' => 50, 'gcego_category' => 48, 'gcego_key' => 120] as $column => $length) {
                if (! Schema::hasColumn('notifications', $column)) {
                    $table->string($column, $length)->nullable();
                }
            }

            if (! Schema::hasColumn('notifications', 'gcego_expires_at')) {
                $table->timestamp('gcego_expires_at')->nullable();
            }
        });

        Schema::table('notifications', static function (Blueprint $table): void {
            $table->unique(['notifiable_type', 'notifiable_id', 'gcego_key'], 'notifications_gcego_key_unique');
            $table->index(['notifiable_type', 'notifiable_id', 'gcego_tenant', 'created_at'], 'notifications_gcego_inbox_index');
            $table->index(['gcego_expires_at'], 'notifications_gcego_expires_index');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', static function (Blueprint $table): void {
            $table->dropUnique('notifications_gcego_key_unique');
            $table->dropIndex('notifications_gcego_inbox_index');
            $table->dropIndex('notifications_gcego_expires_index');
            $table->dropColumn(['gcego_tenant', 'gcego_panel', 'gcego_category', 'gcego_key', 'gcego_expires_at']);
        });
    }
};
