<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Column preferences and saved views of gcego/tables.
 *
 * Owners are (guard, user key as a string, tenant) so integer, UUID and ULID
 * user keys all work; there is no foreign key to a users table because the
 * package cannot know it. Delete a user's rows with
 * PreferenceStore::forgetOwner() and SavedViewRepository::forgetOwner().
 * The tenant is "" when the application has no tenancy.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = config('gcego-tables.storage.connection');
        $schema = Schema::connection(is_string($connection) ? $connection : null);

        $schema->create($this->table('preferences_table', 'gcego_table_preferences'), static function (Blueprint $table): void {
            $table->id();
            $table->string('table_identity', 191);
            $table->string('guard', 64);
            $table->string('owner_key', 191);
            $table->string('tenant_key', 191)->default('');
            $table->unsignedSmallInteger('version');
            $table->json('state');
            $table->timestamps();
            $table->unique(['table_identity', 'guard', 'owner_key', 'tenant_key'], 'gcego_table_preferences_owner_unique');
            $table->index(['guard', 'owner_key'], 'gcego_table_preferences_owner_index');
        });

        $schema->create($this->table('views_table', 'gcego_table_views'), static function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('table_identity', 191);
            $table->string('guard', 64);
            $table->string('owner_key', 191);
            $table->string('tenant_key', 191)->default('');
            $table->string('name', 60);
            $table->unsignedSmallInteger('version');
            $table->json('state');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['table_identity', 'guard', 'owner_key', 'tenant_key', 'name'], 'gcego_table_views_name_unique');
            $table->index(['table_identity', 'guard', 'owner_key', 'tenant_key'], 'gcego_table_views_owner_index');
        });
    }

    public function down(): void
    {
        $connection = config('gcego-tables.storage.connection');
        $schema = Schema::connection(is_string($connection) ? $connection : null);
        $schema->dropIfExists($this->table('views_table', 'gcego_table_views'));
        $schema->dropIfExists($this->table('preferences_table', 'gcego_table_preferences'));
    }

    private function table(string $key, string $default): string
    {
        $table = config('gcego-tables.storage.'.$key, $default);

        return is_string($table) && preg_match('/^[a-z][a-z0-9_]{0,63}$/', $table) === 1 ? $table : $default;
    }
};
