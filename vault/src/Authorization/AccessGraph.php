<?php

declare(strict_types=1);

namespace Gcego\Vault\Authorization;

/**
 * An immutable snapshot of Vault's definitions: the permission registry,
 * the roles, their own permissions and their inheritance. Built from four
 * queries, cached under the global version, and shared by every permission
 * set computed against it. Holds no user data.
 *
 * Inheritance is walked breadth-first with a visited set, so even a cycle
 * that reached the database (it cannot through RoleManager) terminates.
 */
final class AccessGraph
{
    /** @var array<int, list<int>> */
    private array $closures = [];

    /** @var array<string, int>|null */
    private ?array $index = null;

    /**
     * @param  array<int, array{name: string, tenant: string, panel: string|null, super: bool, archived: bool, label: string}>  $roles
     * @param  array<int, list<int>>  $parents  role id => ids of the roles it inherits
     * @param  array<int, list<int>>  $grants  role id => its own permission ids
     * @param  array<int, array{name: string, scope: string, sensitive: bool, requires: list<string>, label: string, group: string|null}>  $permissions
     */
    public function __construct(
        public readonly array $roles,
        public readonly array $parents,
        public readonly array $grants,
        public readonly array $permissions,
    ) {}

    /**
     * @param  array{roles: array<int, array{name: string, tenant: string, panel: string|null, super: bool, archived: bool, label: string}>, parents: array<int, list<int>>, grants: array<int, list<int>>, permissions: array<int, array{name: string, scope: string, sensitive: bool, requires: list<string>, label: string, group: string|null}>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['roles'], $data['parents'], $data['grants'], $data['permissions']);
    }

    /**
     * @return array{roles: array<int, array{name: string, tenant: string, panel: string|null, super: bool, archived: bool, label: string}>, parents: array<int, list<int>>, grants: array<int, list<int>>, permissions: array<int, array{name: string, scope: string, sensitive: bool, requires: list<string>, label: string, group: string|null}>}
     */
    public function toArray(): array
    {
        return ['roles' => $this->roles, 'parents' => $this->parents, 'grants' => $this->grants, 'permissions' => $this->permissions];
    }

    public function permissionId(string $name): ?int
    {
        return $this->permissionIndex()[$name] ?? null;
    }

    /**
     * @return array<string, int> name => id
     */
    public function permissionIndex(): array
    {
        if ($this->index === null) {
            $this->index = [];

            foreach ($this->permissions as $id => $permission) {
                $this->index[$permission['name']] = $id;
            }
        }

        return $this->index;
    }

    public function knows(string $permission): bool
    {
        return $this->permissionId($permission) !== null;
    }

    /**
     * The role and every role it inherits, transitively; archived roles
     * contribute nothing (and neither do the roles reached only through them).
     *
     * @return list<int>
     */
    public function closure(int $role): array
    {
        if (isset($this->closures[$role])) {
            return $this->closures[$role];
        }

        $visited = [];
        $queue = [$role];

        while ($queue !== []) {
            $current = array_shift($queue);

            if (isset($visited[$current]) || ! isset($this->roles[$current]) || $this->roles[$current]['archived']) {
                continue;
            }

            $visited[$current] = true;

            foreach ($this->parents[$current] ?? [] as $parent) {
                $queue[] = $parent;
            }
        }

        return $this->closures[$role] = array_keys($visited);
    }

    /**
     * Whether making $role inherit $parent would create a cycle (or a self-reference).
     */
    public function wouldCycle(int $role, int $parent): bool
    {
        if ($role === $parent) {
            return true;
        }

        $visited = [];
        $queue = [$parent];

        while ($queue !== []) {
            $current = array_shift($queue);

            if ($current === $role) {
                return true;
            }

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            foreach ($this->parents[$current] ?? [] as $next) {
                $queue[] = $next;
            }
        }

        return false;
    }

    /**
     * Every permission a role grants, with where each one comes from.
     *
     * @return array<int, string> permission id => role name it comes from (the role itself or an inherited one)
     */
    public function permissionsOf(int $role): array
    {
        $permissions = [];

        foreach ($this->closure($role) as $member) {
            foreach ($this->grants[$member] ?? [] as $permission) {
                $permissions[$permission] ??= $this->roles[$member]['name'];
            }
        }

        return $permissions;
    }

    public function roleId(string $name, string $tenant = ''): ?int
    {
        $fallback = null;

        foreach ($this->roles as $id => $role) {
            if ($role['name'] === $name) {
                if ($role['tenant'] === $tenant) {
                    return $id;
                }

                if ($role['tenant'] === '') {
                    $fallback = $id;
                }
            }
        }

        return $fallback;
    }
}
