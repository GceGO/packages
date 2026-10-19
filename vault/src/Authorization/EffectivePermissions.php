<?php

declare(strict_types=1);

namespace Gcego\Vault\Authorization;

/**
 * Everything one holder may do in one scope (global, or one tenant) and
 * panel, at the moment it was computed: the roles they hold (directly and
 * through inheritance), each permission with the sources granting it, and
 * whether a super-administrator role applies. Immutable, serializable to a
 * plain array for caching; expiresAt bounds how long it may be reused.
 */
final readonly class EffectivePermissions
{
    /**
     * @param  list<string>  $roles  Roles held directly (active, unarchived).
     * @param  list<string>  $inherited  Roles reached only through inheritance.
     * @param  array<string, list<string>>  $permissions  name => sources ("role:teacher", "role:editor (via manager)", "direct")
     * @param  int|null  $expiresAt  Unix time the earliest temporary access ends.
     */
    public function __construct(
        public string $holder,
        public string $tenant,
        public ?string $panel,
        public array $roles,
        public array $inherited,
        public array $permissions,
        public bool $super,
        public ?int $expiresAt,
        public int $computedAt,
    ) {}

    public function has(string $permission): bool
    {
        return isset($this->permissions[$permission]);
    }

    public function holdsRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->permissions);
    }

    /**
     * @return list<string>
     */
    public function sources(string $permission): array
    {
        return $this->permissions[$permission] ?? [];
    }

    /**
     * @return array{holder: string, tenant: string, panel: string|null, roles: list<string>, inherited: list<string>, permissions: array<string, list<string>>, super: bool, expires_at: int|null, computed_at: int}
     */
    public function toArray(): array
    {
        return [
            'holder' => $this->holder, 'tenant' => $this->tenant, 'panel' => $this->panel, 'roles' => $this->roles, 'inherited' => $this->inherited,
            'permissions' => $this->permissions, 'super' => $this->super, 'expires_at' => $this->expiresAt, 'computed_at' => $this->computedAt,
        ];
    }

    /**
     * @param  array{holder: string, tenant: string, panel: string|null, roles: list<string>, inherited: list<string>, permissions: array<string, list<string>>, super: bool, expires_at: int|null, computed_at: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['holder'], $data['tenant'], $data['panel'], $data['roles'], $data['inherited'], $data['permissions'], $data['super'], $data['expires_at'], $data['computed_at']);
    }
}
