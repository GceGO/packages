<?php

declare(strict_types=1);

namespace Gcego\Security\Permissions;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\AuthorizationEngine;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Exceptions\InvalidPermissionException;

/**
 * Requires several permissions: all of them (default) or any of them.
 *
 *     new Permissions('subjects.view', 'subjects.update')
 *     Permissions::any('reports.view', 'reports.export')
 *     (new Permissions('members.view', 'members.invite'))->inTenant()
 *
 * Immutable: any() and inTenant() return copies.
 */
final class Permissions implements Requirement
{
    /**
     * @var list<string>
     */
    public readonly array $names;

    private bool $any = false;

    private bool $tenant = false;

    public function __construct(string ...$names)
    {
        if ($names === []) {
            throw InvalidPermissionException::invalid('Permissions', 'list at least one permission.');
        }

        $this->names = array_values(array_unique(array_map(static fn (string $name): string => PermissionName::assert($name), $names)));
    }

    public static function any(string ...$names): self
    {
        $permissions = new self(...$names);
        $permissions->any = true;

        return $permissions;
    }

    public function inTenant(): self
    {
        $copy = clone $this;
        $copy->tenant = true;

        return $copy;
    }

    public function requiresAny(): bool
    {
        return $this->any;
    }

    public function isTenant(): bool
    {
        return $this->tenant;
    }

    public function evaluate(AuthorizationEngine $engine, AuthorizationContext $context, mixed $resource = null): AuthorizationDecision
    {
        $last = null;

        foreach ($this->names as $name) {
            $decision = $engine->permission($name, $context, $this->tenant);

            if ($this->any === $decision->allowed) {
                // any: the first grant decides; all: the first denial decides.
                return $decision;
            }

            $last = $decision;
        }

        return $this->any
            ? AuthorizationDecision::deny(implode('|', $this->names), $last->reason ?? DenialReason::MissingPermission, $resource, $last->status, metadata: ['permissions' => $this->names, 'mode' => 'any'])
            : AuthorizationDecision::allow(implode('&', $this->names), $resource, ['permissions' => $this->names]);
    }

    public function describe(): string
    {
        return sprintf('%s%s of %s', $this->tenant ? 'tenant permissions: ' : 'permissions: ', $this->any ? 'any' : 'all', implode(', ', $this->names));
    }

    /**
     * @return array{permissions: list<string>, any: bool, tenant: bool}
     */
    public function normalize(): array
    {
        return ['permissions' => $this->names, 'any' => $this->any, 'tenant' => $this->tenant];
    }
}
