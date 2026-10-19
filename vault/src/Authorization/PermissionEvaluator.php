<?php

declare(strict_types=1);

namespace Gcego\Vault\Authorization;

use Gcego\Vault\Caching\PermissionCache;
use Gcego\Vault\Caching\RequestMemo;
use Gcego\Vault\Models\Assignment;
use Gcego\Vault\Models\Grant;
use Gcego\Vault\Models\Permission;
use Gcego\Vault\Models\Role;
use Gcego\Vault\Support\Holder;
use Gcego\Vault\VaultConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * The one place Vault decides what a holder may do. Resolution is
 * deterministic, in this order:
 *
 * 1. Unknown permission (not registered): no answer (null), which every
 *    caller treats as a denial. Nothing is allowed by default.
 * 2. Scope: only assignments and grants made in the asked scope count.
 *    Global ones answer global questions; a tenant's answer that tenant's
 *    questions. A permission registered for one scope only ("global" or
 *    "tenant") is never granted in the other.
 * 3. Active: revoked, expired (checked against the clock now, not by a job)
 *    and archived roles count for nothing.
 * 4. Panel: a role limited to a panel counts only in that panel.
 * 5. Grants: the union of the held roles' permissions, their inherited
 *    roles' permissions and direct grants. Grants are additive; Vault has no
 *    explicit denials, so no ordering between roles can change an answer.
 *    Restrictions belong in policies, which Vault never overrides.
 * 6. Super administrators (enabled, global scope only, directly held role):
 *    every registered global permission except the excluded patterns.
 *
 * Results are memoized per request and cached across requests under version
 * tokens (see PermissionCache). Scoped: one instance per request or job.
 */
final readonly class PermissionEvaluator
{
    public function __construct(
        private PermissionCache $cache,
        private RequestMemo $memo,
        private VaultConfig $config,
    ) {}

    public function graph(): AccessGraph
    {
        return $this->memo->remember('graph', function (): AccessGraph {
            $key = $this->cache->key('graph', $this->globalVersion());
            $data = $this->cache->get($key);

            if (is_array($data) && isset($data['roles'], $data['parents'], $data['grants'], $data['permissions'])) {
                /** @var array{roles: array<int, array{name: string, tenant: string, panel: string|null, super: bool, archived: bool, label: string}>, parents: array<int, list<int>>, grants: array<int, list<int>>, permissions: array<int, array{name: string, scope: string, sensitive: bool, requires: list<string>, label: string, group: string|null}>} $data */
                return AccessGraph::fromArray($data);
            }

            $graph = $this->load();
            $this->cache->put($key, $graph->toArray(), $this->config->cacheTtl);

            return $graph;
        });
    }

    public function effective(Model|Holder $holder, string $tenant = '', ?string $panel = null): EffectivePermissions
    {
        $holder = $holder instanceof Holder ? $holder : Holder::of($holder);

        return $this->memo->remember('eff|'.$holder->key().'|'.$tenant.'|'.($panel ?? ''), function () use ($holder, $tenant, $panel): EffectivePermissions {
            $graph = $this->graph();
            $key = $this->cache->key('eff', sha1($holder->key()), sha1($tenant.'|'.($panel ?? '')), $this->globalVersion(), $this->cache->holderVersion($holder->type, $holder->id));
            $now = Date::now()->getTimestamp();
            $cached = $this->cache->get($key);

            if (is_array($cached) && isset($cached['holder'], $cached['permissions']) && ($cached['expires_at'] === null || $cached['expires_at'] > $now)) {
                /** @var array{holder: string, tenant: string, panel: string|null, roles: list<string>, inherited: list<string>, permissions: array<string, list<string>>, super: bool, expires_at: int|null, computed_at: int} $cached */
                return EffectivePermissions::fromArray($cached);
            }

            $permissions = $this->compute($graph, $holder, $tenant, $panel);
            $this->cache->put($key, $permissions->toArray(), $permissions->expiresAt === null ? $this->config->cacheTtl : $permissions->expiresAt - $now);

            return $permissions;
        });
    }

    /**
     * True or false, or null when the permission is not registered (a denial for every caller).
     */
    public function allows(Model|Holder $holder, string $permission, string $tenant = '', ?string $panel = null): ?bool
    {
        return $this->explain($holder, $permission, $tenant, $panel)->allowed ?: ($this->graph()->knows($permission) ? false : null);
    }

    public function explain(Model|Holder $holder, string $permission, string $tenant = '', ?string $panel = null): Explanation
    {
        $graph = $this->graph();
        $id = $graph->permissionId($permission);

        if ($id === null) {
            return new Explanation($permission, false, Explanation::UnknownPermission, $tenant, $panel);
        }

        $effective = $this->effective($holder, $tenant, $panel);

        if ($effective->has($permission)) {
            return new Explanation($permission, true, Explanation::Granted, $tenant, $panel, $effective->sources($permission));
        }

        $scope = $graph->permissions[$id]['scope'];

        if (! $this->appliesIn($scope, $tenant)) {
            return new Explanation($permission, false, Explanation::WrongScope, $tenant, $panel);
        }

        if ($effective->super) {
            return $this->config->superExcludes($permission)
                ? new Explanation($permission, false, Explanation::SuperExcluded, $tenant, $panel)
                : new Explanation($permission, true, Explanation::SuperAdministrator, $tenant, $panel, array_map(static fn (string $role): string => 'role:'.$role, $effective->roles));
        }

        return new Explanation($permission, false, Explanation::NotGranted, $tenant, $panel);
    }

    /**
     * Compute (and memoize) the permission sets of many holders with two queries, for lists.
     *
     * @param  iterable<Model>  $holders
     */
    public function preload(iterable $holders, string $tenant = '', ?string $panel = null): void
    {
        $byType = [];

        foreach ($holders as $model) {
            $holder = Holder::of($model);
            $byType[$holder->type][$holder->id] = $holder;
        }

        $graph = $this->graph();

        foreach ($byType as $type => $holdersOfType) {
            $ids = array_map('strval', array_keys($holdersOfType));
            $assignments = Assignment::query()->where('model_type', $type)->whereIn('model_id', $ids)->where('tenant', $tenant)->active()->get(['model_id', 'role_id', 'expires_at'])->groupBy('model_id');
            $grants = Grant::query()->where('model_type', $type)->whereIn('model_id', $ids)->where('tenant', $tenant)->active()->get(['model_id', 'permission_id', 'expires_at'])->groupBy('model_id');

            foreach ($holdersOfType as $id => $holder) {
                $this->memo->remember('eff|'.$holder->key().'|'.$tenant.'|'.($panel ?? ''), fn (): EffectivePermissions => $this->build(
                    $graph,
                    $holder,
                    $tenant,
                    $panel,
                    $assignments->get((string) $id)?->all() ?? [],
                    $grants->get((string) $id)?->all() ?? [],
                ));
            }
        }
    }

    /**
     * Forget this request's memory (after a change made in the same request).
     */
    public function forget(): void
    {
        $this->memo->flush();
    }

    public function appliesIn(string $permissionScope, string $tenant): bool
    {
        return $permissionScope === Permission::ScopeAny || ($tenant === '' ? $permissionScope === Permission::ScopeGlobal : $permissionScope === Permission::ScopeTenant);
    }

    private function compute(AccessGraph $graph, Holder $holder, string $tenant, ?string $panel): EffectivePermissions
    {
        $assignments = Assignment::query()->heldBy($holder->type, $holder->id)->where('tenant', $tenant)->active()->get(['role_id', 'expires_at'])->all();
        $grants = Grant::query()->heldBy($holder->type, $holder->id)->where('tenant', $tenant)->active()->get(['permission_id', 'expires_at'])->all();

        return $this->build($graph, $holder, $tenant, $panel, $assignments, $grants);
    }

    /**
     * @param  list<Assignment>  $assignments
     * @param  list<Grant>  $grants
     */
    private function build(AccessGraph $graph, Holder $holder, string $tenant, ?string $panel, array $assignments, array $grants): EffectivePermissions
    {
        $roles = [];
        $inherited = [];
        $permissions = [];
        $super = false;
        $expires = null;
        $soonest = static function (?Carbon $at) use (&$expires): void {
            if ($at !== null) {
                $expires = $expires === null ? $at->getTimestamp() : min($expires, $at->getTimestamp());
            }
        };

        foreach ($assignments as $assignment) {
            $roleId = $assignment->role_id;
            $role = $graph->roles[$roleId] ?? null;

            if ($role === null || $role['archived'] || ! $this->roleApplies($role, $tenant, $panel)) {
                continue;
            }

            $roles[] = $role['name'];
            $soonest($assignment->expires_at);
            $super = $super || ($role['super'] && $tenant === '' && $this->config->superAdmin);

            foreach ($graph->closure($roleId) as $member) {
                $memberRole = $graph->roles[$member];

                if ($member !== $roleId) {
                    if (! $this->roleApplies($memberRole, $tenant, $panel)) {
                        continue;
                    }

                    $inherited[] = $memberRole['name'];
                }

                foreach ($graph->grants[$member] ?? [] as $permissionId) {
                    $permission = $graph->permissions[$permissionId] ?? null;

                    if ($permission === null || ! $this->appliesIn($permission['scope'], $tenant)) {
                        continue;
                    }

                    $permissions[$permission['name']][] = $member === $roleId ? 'role:'.$role['name'] : 'role:'.$memberRole['name'].' (via '.$role['name'].')';
                }
            }
        }

        foreach ($grants as $grant) {
            $permission = $graph->permissions[$grant->permission_id] ?? null;

            if ($permission === null || ! $this->appliesIn($permission['scope'], $tenant)) {
                continue;
            }

            $permissions[$permission['name']][] = 'direct';
            $soonest($grant->expires_at);
        }

        ksort($permissions);

        foreach ($permissions as $name => $sources) {
            $permissions[$name] = array_values(array_unique($sources));
        }

        $roles = array_values(array_unique($roles));

        return new EffectivePermissions(
            holder: $holder->key(),
            tenant: $tenant,
            panel: $panel,
            roles: $roles,
            inherited: array_values(array_diff(array_unique($inherited), $roles)),
            permissions: $permissions,
            super: $super,
            expiresAt: $expires,
            computedAt: Date::now()->getTimestamp(),
        );
    }

    /**
     * @param  array{name: string, tenant: string, panel: string|null, super: bool, archived: bool, label: string}  $role
     */
    private function roleApplies(array $role, string $tenant, ?string $panel): bool
    {
        return ($role['tenant'] === '' || $role['tenant'] === $tenant) && ($role['panel'] === null || $role['panel'] === $panel);
    }

    private function globalVersion(): string
    {
        return $this->memo->remember('version', fn (): string => $this->cache->globalVersion());
    }

    private function load(): AccessGraph
    {
        $roles = [];
        $parents = [];
        $grants = [];
        $permissions = [];

        foreach (Role::query()->get(['id', 'name', 'label', 'tenant', 'panel', 'is_super', 'archived_at']) as $role) {
            $roles[$role->id] = ['name' => $role->name, 'tenant' => $role->tenant, 'panel' => $role->panel, 'super' => $role->is_super, 'archived' => $role->archived_at !== null, 'label' => $role->label];
        }

        $connection = (new Role)->getConnection();

        foreach ($connection->table('gcego_vault_role_inheritance')->get(['role_id', 'inherits_id']) as $edge) {
            $parents[(int) $edge->role_id][] = (int) $edge->inherits_id;
        }

        foreach ($connection->table('gcego_vault_role_permissions')->get(['role_id', 'permission_id']) as $grant) {
            $grants[(int) $grant->role_id][] = (int) $grant->permission_id;
        }

        foreach (Permission::query()->get(['id', 'name', 'label', 'group', 'scope', 'is_sensitive', 'requires']) as $permission) {
            $permissions[$permission->id] = ['name' => $permission->name, 'scope' => $permission->scope, 'sensitive' => $permission->is_sensitive, 'requires' => $permission->dependencies(), 'label' => $permission->label, 'group' => $permission->group];
        }

        return new AccessGraph($roles, $parents, $grants, $permissions);
    }
}
