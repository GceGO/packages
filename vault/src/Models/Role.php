<?php

declare(strict_types=1);

namespace Gcego\Vault\Models;

use Gcego\Vault\Models\Concerns\UsesVaultConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A role: a named set of permissions, optionally inheriting other roles'.
 *
 * - tenant "" is usable everywhere (assigned globally or inside any tenant);
 *   a tenant id means the tenant defined it and it is assignable there only.
 * - panel limits its permissions to one panel.
 * - System roles come from configuration (gcego:vault:sync) and cannot be
 *   deleted; protected roles keep at least one active holder.
 *
 * Written only through RoleManager (never mass-assigned).
 *
 * @property int $id
 * @property string $name
 * @property string $label
 * @property string|null $description
 * @property string $tenant
 * @property string|null $panel
 * @property string|null $group
 * @property bool $is_system
 * @property bool $is_protected
 * @property bool $is_super
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Role extends Model
{
    use UsesVaultConnection;

    protected $table = 'gcego_vault_roles';

    protected $guarded = ['*'];

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'gcego_vault_role_permissions', 'role_id', 'permission_id');
    }

    /**
     * The roles whose permissions this role includes.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'gcego_vault_role_inheritance', 'role_id', 'inherits_id');
    }

    /**
     * The roles that include this role's permissions.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'gcego_vault_role_inheritance', 'inherits_id', 'role_id');
    }

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'role_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeUnarchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * Roles that may be assigned in this scope ("" global, or a tenant id).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAvailableIn(Builder $query, string $tenant): Builder
    {
        return $tenant === '' ? $query->where('tenant', '') : $query->whereIn('tenant', ['', $tenant]);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function availableIn(string $tenant): bool
    {
        return $this->tenant === '' || $this->tenant === $tenant;
    }

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'is_protected' => 'boolean', 'is_super' => 'boolean', 'metadata' => 'array', 'archived_at' => 'datetime'];
    }
}
