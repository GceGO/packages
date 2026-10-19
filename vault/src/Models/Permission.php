<?php

declare(strict_types=1);

namespace Gcego\Vault\Models;

use Gcego\Vault\Models\Concerns\UsesVaultConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A registered permission. Registering (from code, discovery or the registry
 * screen) never grants it to anyone. Written only through PermissionRegistry.
 *
 * @property int $id
 * @property string $name
 * @property string $normalized
 * @property string $label
 * @property string|null $description
 * @property string|null $group
 * @property string|null $resource
 * @property string|null $ability
 * @property string $scope
 * @property string $source
 * @property bool $is_sensitive
 * @property list<string>|null $requires
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $declared_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Permission extends Model
{
    use UsesVaultConnection;

    public const string ScopeGlobal = 'global';

    public const string ScopeTenant = 'tenant';

    public const string ScopeAny = 'any';

    protected $table = 'gcego_vault_permissions';

    protected $guarded = ['*'];

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'gcego_vault_role_permissions', 'permission_id', 'role_id');
    }

    /**
     * @return HasMany<Grant, $this>
     */
    public function grants(): HasMany
    {
        return $this->hasMany(Grant::class, 'permission_id');
    }

    /**
     * Whether a grant made at this scope ("" global, or a tenant id) takes effect.
     */
    public function appliesIn(string $tenant): bool
    {
        return $this->scope === self::ScopeAny || ($tenant === '' ? $this->scope === self::ScopeGlobal : $this->scope === self::ScopeTenant);
    }

    /**
     * @return list<string>
     */
    public function dependencies(): array
    {
        return array_values(array_filter($this->requires ?? [], 'is_string'));
    }

    protected function casts(): array
    {
        return ['is_sensitive' => 'boolean', 'requires' => 'array', 'metadata' => 'array', 'declared_at' => 'datetime'];
    }
}
