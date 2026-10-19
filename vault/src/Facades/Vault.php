<?php

declare(strict_types=1);

namespace Gcego\Vault\Facades;

use Gcego\Vault\Vault as VaultManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static bool allows(\Illuminate\Database\Eloquent\Model $holder, string $permission, \Gcego\Security\Tenancy\Tenant|string|null $tenant = null)
 * @method static \Gcego\Vault\Authorization\Explanation explain(\Illuminate\Database\Eloquent\Model $holder, string $permission, \Gcego\Security\Tenancy\Tenant|string|null $tenant = null, ?string $panel = null)
 * @method static \Gcego\Vault\Authorization\EffectivePermissions effective(\Illuminate\Database\Eloquent\Model $holder, \Gcego\Security\Tenancy\Tenant|string|null $tenant = null, ?string $panel = null)
 * @method static bool can(string $permission, bool $tenant = false)
 * @method static \Gcego\Vault\Roles\RoleManager roles()
 * @method static \Gcego\Vault\Permissions\PermissionRegistry permissions()
 * @method static \Gcego\Vault\Assignments\AssignmentManager assignments()
 * @method static \Gcego\Vault\Governance\AccessRequests requests()
 * @method static \Gcego\Vault\Authorization\PermissionEvaluator evaluator()
 * @method static array sync(?\Illuminate\Database\Eloquent\Model $by = null, bool $prune = false)
 * @method static void flush(?\Illuminate\Database\Eloquent\Model $by = null)
 *
 * @see VaultManager
 */
final class Vault extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return VaultManager::class;
    }
}
