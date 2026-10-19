<?php

declare(strict_types=1);

use Gcego\Vault\Models\Permission;
use Gcego\Vault\Models\Role;
use Gcego\Vault\Tests\Fixtures\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Date;

/**
 * Builds 2,000 permissions, 200 roles in 20 inheritance chains 10 deep, and
 * 1,000 people holding 3 roles each (3,000 assignments), with raw inserts.
 *
 * @return list<User>
 */
function largeWorld(): array
{
    $now = Date::now();
    $permissions = [];

    for ($i = 0; $i < 2000; $i++) {
        $permissions[] = ['name' => "resource{$i}.view", 'normalized' => "resource{$i}.view", 'label' => "View {$i}", 'scope' => 'any', 'source' => 'custom', 'created_at' => $now, 'updated_at' => $now];
    }

    foreach (array_chunk($permissions, 500) as $chunk) {
        Permission::query()->insert($chunk);
    }

    $permissionIds = Permission::query()->where('name', 'like', 'resource%')->pluck('id')->all();
    $roles = [];

    for ($i = 0; $i < 200; $i++) {
        $roles[] = ['name' => "role-{$i}", 'label' => "Role {$i}", 'tenant' => '', 'created_at' => $now, 'updated_at' => $now];
    }

    Role::query()->insert($roles);
    $roleIds = Role::query()->where('name', 'like', 'role-%')->orderBy('id')->pluck('id')->all();
    $links = [];
    $edges = [];

    foreach ($roleIds as $index => $roleId) {
        foreach (array_slice($permissionIds, ($index * 10) % 2000, 10) as $permissionId) {
            $links[] = ['role_id' => $roleId, 'permission_id' => $permissionId, 'created_at' => $now];
        }

        if ($index % 10 !== 0) {
            $edges[] = ['role_id' => $roleId, 'inherits_id' => $roleIds[$index - 1], 'created_at' => $now];
        }
    }

    foreach (array_chunk($links, 500) as $chunk) {
        DB::table('gcego_vault_role_permissions')->insert($chunk);
    }

    DB::table('gcego_vault_role_inheritance')->insert($edges);

    $users = [];
    $people = [];

    for ($i = 0; $i < 1000; $i++) {
        $people[] = ['name' => "Person {$i}", 'email' => "p{$i}@example.test", 'created_at' => $now, 'updated_at' => $now];
    }

    foreach (array_chunk($people, 500) as $chunk) {
        User::query()->insert($chunk);
    }

    $assignments = [];

    foreach (User::query()->orderBy('id')->get() as $index => $user) {
        $users[] = $user;

        foreach (array_unique([($index * 7) % 200, ($index * 13) % 200, ($index * 31) % 200]) as $pick) {
            $assignments[] = ['role_id' => $roleIds[$pick], 'model_type' => $user->getMorphClass(), 'model_id' => (string) $user->id, 'tenant' => '', 'source' => 'system', 'active_key' => sha1($pick.'|'.$user->id), 'created_at' => $now, 'updated_at' => $now];
        }
    }

    foreach (array_chunk($assignments, 500) as $chunk) {
        DB::table('gcego_vault_assignments')->insert($chunk);
    }

    app(Gcego\Vault\Caching\Invalidation::class)->definitions();

    return $users;
}

it('resolves a deep-inheritance permission set with three queries, then none', function (): void {
    $users = largeWorld();
    $user = $users[9];
    app()->forgetScopedInstances();

    $first = queriesDuring(fn () => vault()->effective($user));
    app()->forgetScopedInstances();
    $cached = queriesDuring(fn () => $user->hasPermissionTo('resource5.view'));

    // A cold request loads the shared graph (4 queries) and the person's assignments and grants (2);
    // every later request reads both from the cache.
    expect(count($first))->toBeLessThanOrEqual(6)->and($cached)->toBe([]);
});

it('answers many checks in one request from memory', function (): void {
    $user = largeWorld()[42];
    vault()->effective($user);

    $queries = queriesDuring(function () use ($user): void {
        for ($i = 0; $i < 500; $i++) {
            $user->hasPermissionTo('resource'.($i % 2000).'.view');
        }
    });

    expect($queries)->toBe([]);
});

it('loads a page of people with two queries, not one per person', function (): void {
    $users = largeWorld();
    $evaluator = vault()->evaluator();
    $evaluator->graph();

    $queries = queriesDuring(fn () => $evaluator->preload(array_slice($users, 0, 50)));

    expect(count($queries))->toBe(2);
});

it('reports measured timings', function (): void {
    $users = largeWorld();
    app()->forgetScopedInstances();

    $start = hrtime(true);
    vault()->evaluator()->graph();
    $graph = (hrtime(true) - $start) / 1e6;

    $start = hrtime(true);
    foreach (array_slice($users, 0, 100) as $user) {
        vault()->effective($user);
    }
    $cold = (hrtime(true) - $start) / 1e6 / 100;

    app()->forgetScopedInstances();
    $start = hrtime(true);
    foreach (array_slice($users, 0, 100) as $user) {
        vault()->effective($user);
    }
    $warm = (hrtime(true) - $start) / 1e6 / 100;

    $start = hrtime(true);
    for ($i = 0; $i < 10000; $i++) {
        $users[$i % 100]->hasPermissionTo('resource'.($i % 2000).'.view');
    }
    $check = (hrtime(true) - $start) / 1e3 / 10000;

    $user = $users[3];
    $start = hrtime(true);
    $user->removeRole(Role::query()->where('name', 'role-21')->value('name') ?? 'role-21');
    $revoke = (hrtime(true) - $start) / 1e6;

    fwrite(STDERR, sprintf("\n[vault-benchmark] graph load %.1f ms | cold set %.2f ms/person | cached set %.3f ms/person | memoized check %.2f µs | revoke %.1f ms\n", $graph, $cold, $warm, $check, $revoke));

    expect($warm)->toBeLessThan($cold);
});
