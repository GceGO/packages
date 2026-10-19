<?php

declare(strict_types=1);

namespace Gcego\Security\Permissions;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\AuthorizationEngine;
use Gcego\Security\Contracts\Requirement;

/**
 * Requires one permission:
 *
 *     new Permission('subjects.view')                    // global: the configured PermissionProvider
 *     Permission::inTenant('institution.members.view')   // the active tenant's verified membership only
 *
 * Tenant permissions require a selected tenant and a membership that grants
 * them; global roles and Gate::before rules cannot satisfy them.
 */
final readonly class Permission implements Requirement
{
    public function __construct(public string $name, public bool $tenant = false)
    {
        PermissionName::assert($name);
    }

    public static function inTenant(string $name): self
    {
        return new self($name, true);
    }

    public function evaluate(AuthorizationEngine $engine, AuthorizationContext $context, mixed $resource = null): AuthorizationDecision
    {
        return $engine->permission($this->name, $context, $this->tenant);
    }

    public function describe(): string
    {
        return ($this->tenant ? 'tenant permission ' : 'permission ').$this->name;
    }

    /**
     * @return array{permission: string, tenant: bool}
     */
    public function normalize(): array
    {
        return ['permission' => $this->name, 'tenant' => $this->tenant];
    }
}
