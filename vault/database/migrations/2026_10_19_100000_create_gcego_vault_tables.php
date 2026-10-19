<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vault's storage: the permission registry, roles with their permissions and
 * inheritance, role assignments and direct permission grants (global or per
 * tenant, optionally temporary, never deleted: revoked rows are the history),
 * access requests with their approvals, and the append-only audit log.
 *
 * Scope columns ("tenant") hold "" for global and a gcego/security tenant id
 * ("institution:12") otherwise, so unique indexes never meet NULLs. Holder
 * keys are strings, so integer, ULID and UUID keys all fit. "active_key" is
 * set only while an assignment is active (NULL once revoked or closed): the
 * unique index makes duplicate concurrent grants impossible.
 *
 * Nothing here touches existing tables: no users column, no other package's data.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        $connection = config('gcego-vault.connection');

        return is_string($connection) ? $connection : null;
    }

    public function up(): void
    {
        Schema::create('gcego_vault_permissions', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            // Lowercase name: "subjects.viewAny" and "subjects.viewany" can never both exist.
            $table->string('normalized', 150)->unique('gcego_vault_permissions_normalized_unique');
            $table->string('label', 150);
            $table->text('description')->nullable();
            $table->string('group', 100)->nullable();
            $table->string('resource', 100)->nullable();
            $table->string('ability', 100)->nullable();
            // global, tenant or any: where a grant of this permission takes effect.
            $table->string('scope', 10)->default('any');
            // config, discovered or custom.
            $table->string('source', 20)->default('custom');
            $table->boolean('is_sensitive')->default(false);
            $table->json('requires')->nullable();
            $table->json('metadata')->nullable();
            // Last time code or discovery declared it (null: only stored).
            $table->timestamp('declared_at')->nullable();
            $table->timestamps();

            $table->index(['group', 'name'], 'gcego_vault_permissions_group_index');
        });

        Schema::create('gcego_vault_roles', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('label', 150);
            $table->text('description')->nullable();
            // "" for roles usable everywhere, or the tenant that defined (and owns) the role.
            $table->string('tenant', 100)->default('');
            // A panel name: the role's permissions apply only inside that panel.
            $table->string('panel', 64)->nullable();
            $table->string('group', 100)->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_protected')->default(false);
            $table->boolean('is_super')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant', 'name'], 'gcego_vault_roles_tenant_name_unique');
            $table->index(['archived_at'], 'gcego_vault_roles_archived_index');
        });

        Schema::create('gcego_vault_role_permissions', static function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained('gcego_vault_roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('gcego_vault_permissions')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->primary(['role_id', 'permission_id']);
            $table->index(['permission_id'], 'gcego_vault_role_permissions_permission_index');
        });

        // role_id includes every permission of inherits_id (and of its own ancestors).
        Schema::create('gcego_vault_role_inheritance', static function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained('gcego_vault_roles')->cascadeOnDelete();
            $table->foreignId('inherits_id')->constrained('gcego_vault_roles')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->primary(['role_id', 'inherits_id']);
        });

        foreach (['gcego_vault_assignments' => ['role_id', 'gcego_vault_roles'], 'gcego_vault_grants' => ['permission_id', 'gcego_vault_permissions']] as $name => [$column, $target]) {
            Schema::create($name, static function (Blueprint $table) use ($name, $column, $target): void {
                $table->id();
                $table->foreignId($column)->constrained($target)->cascadeOnDelete();
                $table->string('model_type');
                $table->string('model_id', 64);
                $table->string('tenant', 100)->default('');
                $table->timestamp('expires_at')->nullable();
                // manual, system, request, import or sync.
                $table->string('source', 20)->default('manual');
                $table->unsignedBigInteger('request_id')->nullable();
                $table->string('reason', 500)->nullable();
                $table->string('granted_by_type')->nullable();
                $table->string('granted_by_id', 64)->nullable();
                $table->timestamp('revoked_at')->nullable();
                // revoked or expired.
                $table->string('ended_as', 10)->nullable();
                $table->string('revoked_by_type')->nullable();
                $table->string('revoked_by_id', 64)->nullable();
                $table->string('revoke_reason', 500)->nullable();
                $table->string('active_key', 64)->nullable()->unique($name.'_active_unique');
                $table->timestamps();

                $table->index(['model_type', 'model_id', 'tenant', 'revoked_at'], $name.'_holder_index');
                $table->index([$column, 'revoked_at'], $name.'_target_index');
                $table->index(['expires_at'], $name.'_expires_index');
            });
        }

        Schema::create('gcego_vault_requests', static function (Blueprint $table): void {
            $table->id();
            $table->string('requester_type');
            $table->string('requester_id', 64);
            $table->string('subject_type');
            $table->string('subject_id', 64);
            $table->foreignId('role_id')->nullable()->constrained('gcego_vault_roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->nullable()->constrained('gcego_vault_permissions')->cascadeOnDelete();
            $table->string('tenant', 100)->default('');
            $table->text('reason');
            $table->timestamp('requested_until')->nullable();
            // pending, approved, rejected, cancelled.
            $table->string('status', 12)->default('pending');
            $table->unsignedTinyInteger('approvals_required')->default(1);
            $table->boolean('is_sensitive')->default(false);
            $table->timestamp('approved_until')->nullable();
            $table->string('decided_by_type')->nullable();
            $table->string('decided_by_id', 64)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_reason', 500)->nullable();
            $table->unsignedBigInteger('assignment_id')->nullable();
            $table->unsignedBigInteger('grant_id')->nullable();
            // Set while pending: one open request per beneficiary, item and tenant.
            $table->string('pending_key', 64)->nullable()->unique('gcego_vault_requests_pending_unique');
            $table->timestamps();

            $table->index(['status', 'tenant'], 'gcego_vault_requests_status_index');
            $table->index(['requester_type', 'requester_id'], 'gcego_vault_requests_requester_index');
        });

        Schema::create('gcego_vault_approvals', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('gcego_vault_requests')->cascadeOnDelete();
            $table->string('approver_type');
            $table->string('approver_id', 64);
            // approve or reject.
            $table->string('decision', 8);
            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['request_id', 'approver_type', 'approver_id'], 'gcego_vault_approvals_approver_unique');
        });

        Schema::create('gcego_vault_audits', static function (Blueprint $table): void {
            $table->id();
            $table->string('event', 60);
            $table->string('actor_type')->nullable();
            $table->string('actor_id', 64)->nullable();
            $table->string('actor_name', 100)->nullable();
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->string('subject_name', 100)->nullable();
            // "role:teacher", "permission:subjects.update", "request:12".
            $table->string('target', 200)->nullable();
            $table->string('tenant', 100)->default('');
            $table->string('reason', 500)->nullable();
            // Identifiers and names only: never credentials, tokens or record attributes.
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['event', 'created_at'], 'gcego_vault_audits_event_index');
            $table->index(['subject_type', 'subject_id'], 'gcego_vault_audits_subject_index');
            $table->index(['tenant', 'created_at'], 'gcego_vault_audits_tenant_index');
            $table->index(['created_at'], 'gcego_vault_audits_created_index');
        });
    }

    public function down(): void
    {
        foreach (['gcego_vault_audits', 'gcego_vault_approvals', 'gcego_vault_requests', 'gcego_vault_grants', 'gcego_vault_assignments', 'gcego_vault_role_inheritance', 'gcego_vault_role_permissions', 'gcego_vault_roles', 'gcego_vault_permissions'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
