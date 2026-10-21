<?php

declare(strict_types=1);

use Gcego\Components\ComponentServiceProvider;
use Gcego\Security\SecurityManager;
use Gcego\Vault\Caching\Invalidation;
use Gcego\Vault\Models\Role;
use Gcego\Vault\Permissions\VaultPermissions;
use Gcego\Vault\Tests\Fixtures\Models\Membership;
use Gcego\Vault\Tests\Fixtures\Models\User;
use Gcego\Vault\Tests\TestCase;
use Gcego\Vault\Vault;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Facade;

// Dataset declarations can construct components before the test application starts.
$componentApp = new Application(dirname(__DIR__));
Facade::setFacadeApplication($componentApp);
$componentApp->register(ComponentServiceProvider::class);

pest()->extend(TestCase::class)->in('Unit', 'Integration', 'Feature', 'Security', 'Performance');

function vault(): Vault
{
    return app(Vault::class);
}

/**
 * A user, optionally a member of institutions.
 *
 * @param  list<int>  $institutions
 */
function person(string $name, array $institutions = []): User
{
    $user = User::query()->create(['name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)).'@example.test']);

    foreach ($institutions as $institution) {
        Membership::query()->create(['user_id' => $user->id, 'institution_id' => $institution]);
    }

    return $user;
}

/**
 * Registers the discovered and configured permissions (what gcego:vault:sync does).
 */
function synced(): void
{
    vault()->sync();
}

/**
 * A custom role with these permissions (trusted setup).
 *
 * @param  list<string>  $permissions
 * @param  list<string>  $inherits
 */
function role(string $name, array $permissions = [], array $inherits = [], string $tenant = ''): Role
{
    $role = new Role;
    $role->forceFill(['name' => $name, 'label' => ucfirst($name), 'tenant' => $tenant])->save();
    $connection = $role->getConnection();

    foreach ($permissions as $permission) {
        $connection->table('gcego_vault_role_permissions')->insert(['role_id' => $role->id, 'permission_id' => vault()->permissions()->require($permission)->id]);
    }

    foreach ($inherits as $parent) {
        $connection->table('gcego_vault_role_inheritance')->insert(['role_id' => $role->id, 'inherits_id' => Role::query()->where('name', $parent)->value('id')]);
    }

    app(Invalidation::class)->definitions();

    return $role;
}

/**
 * A Vault administrator: holds every vault.* permission and everything given in $also.
 *
 * @param  list<string>  $also
 */
function administrator(string $name = 'Admin', array $also = [], array $institutions = []): User
{
    $permissions = [...array_keys(VaultPermissions::definitions()), ...$also];
    $role = Role::query()->where('name', 'vault-admin')->first() ?? role('vault-admin', $permissions);
    $user = person($name, $institutions);
    $user->assignRole($role);

    return $user;
}

/**
 * A new "request": forget scoped state and guards, act as the user, optionally in a tenant.
 */
function actAs(?User $user, ?int $institution = null): void
{
    app()->forgetScopedInstances();
    app('auth')->forgetGuards();

    if ($user !== null) {
        test()->actingAs($user);
    }

    if ($institution !== null && $user !== null) {
        app(SecurityManager::class)->enterTenant((string) $institution);
    }
}

/**
 * @return list<string>
 */
function queriesDuring(Closure $callback): array
{
    $queries = [];
    $recording = true;
    Event::listen(QueryExecuted::class, static function (QueryExecuted $query) use (&$queries, &$recording): void {
        if ($recording) {
            $queries[] = $query->sql;
        }
    });

    try {
        $callback();
    } finally {
        $recording = false;
    }

    return $queries;
}
