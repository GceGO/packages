<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stored setting overrides (one row per key and scope instance, versioned),
 * their append-only history, and changes waiting for an approval workflow.
 * Scope keys are "" (global), a panel name, a tenant id ("institution:12")
 * or a user identity, so integer, ULID and UUID keys all fit.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        $connection = config('gcego-settings.storage.connection');

        return is_string($connection) ? $connection : null;
    }

    public function up(): void
    {
        Schema::create('gcego_settings', static function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64);
            $table->string('scope', 16);
            $table->string('scope_key', 191)->default('');
            // JSON text (never PHP serialization); for sensitive settings, Laravel-encrypted JSON text.
            $table->longText('value');
            $table->string('type', 16);
            $table->boolean('encrypted')->default(false);
            // Starts at 1 and grows with every write: the optimistic concurrency token.
            $table->unsignedInteger('version')->default(1);
            $table->string('updated_by', 191)->nullable();
            $table->timestamps();

            $table->unique(['key', 'scope', 'scope_key'], 'gcego_settings_key_scope_unique');
            $table->index(['scope', 'scope_key'], 'gcego_settings_scope_index');
        });

        Schema::create('gcego_setting_changes', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('key', 64);
            $table->string('group', 64)->nullable();
            $table->string('scope', 16);
            $table->string('scope_key', 191)->default('');
            // The tenant the change belongs to (tenant scope), for tenant-isolated history.
            $table->string('tenant', 100)->nullable();
            $table->string('change', 16);
            $table->string('actor_type')->nullable();
            $table->string('actor_id', 64)->nullable();
            $table->string('actor_name', 64)->nullable();
            // Safe JSON values of non-sensitive settings only; sensitive changes keep "redacted" and no values.
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->boolean('redacted')->default(false);
            $table->unsignedInteger('version')->default(0);
            $table->uuid('correlation');
            $table->timestamp('created_at')->nullable();

            $table->index(['scope', 'scope_key', 'id'], 'gcego_setting_changes_scope_index');
            $table->index(['key', 'id'], 'gcego_setting_changes_key_index');
            $table->index('correlation');
        });

        Schema::create('gcego_setting_requests', static function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('key', 64);
            $table->string('scope', 16);
            $table->string('scope_key', 191)->default('');
            $table->string('tenant', 100)->nullable();
            // Always encrypted: the requested value until it is applied or discarded.
            $table->longText('value')->nullable();
            $table->string('type', 16);
            // The version the requester saw (0: no override); a different version at apply time is a conflict.
            $table->unsignedInteger('base_version')->default(0);
            $table->string('workflow', 64);
            $table->string('status', 16);
            $table->string('requested_by_type')->nullable();
            $table->string('requested_by_id', 64)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['key', 'scope', 'scope_key', 'status'], 'gcego_setting_requests_pending_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gcego_setting_requests');
        Schema::dropIfExists('gcego_setting_changes');
        Schema::dropIfExists('gcego_settings');
    }
};
