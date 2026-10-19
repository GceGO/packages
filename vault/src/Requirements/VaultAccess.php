<?php

declare(strict_types=1);

namespace Gcego\Vault\Requirements;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\AuthorizationEngine;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Contracts\Requirement;
use Gcego\Vault\Governance\Authority;
use Gcego\Vault\Tenancy\Scope;

/**
 * A gcego/security requirement: the actor holds a Vault permission where
 * the request acts — in the active tenant (as a verified member) when one is
 * selected, globally otherwise. Used by the Vault screens and their
 * navigation; usable on any definition:
 *
 *     new VaultAccess('vault.roles.viewAny')
 */
final readonly class VaultAccess implements Requirement
{
    public function __construct(public string $permission) {}

    public function evaluate(AuthorizationEngine $engine, AuthorizationContext $context, mixed $resource = null): AuthorizationDecision
    {
        $scope = Scope::of($context->membership === null ? null : $context->tenant);
        $metadata = ['permission' => $this->permission, 'scope' => $scope === '' ? 'global' : 'tenant'];

        if ($context->user === null) {
            return AuthorizationDecision::deny($this->permission, DenialReason::Unauthenticated, status: $engine->strategy(), metadata: $metadata);
        }

        return app(Authority::class)->holds($context->user, $this->permission, $scope)
            ? AuthorizationDecision::allow($this->permission, metadata: $metadata)
            : AuthorizationDecision::deny($this->permission, DenialReason::MissingPermission, status: $engine->strategy(), metadata: $metadata);
    }

    public function describe(): string
    {
        return 'vault permission '.$this->permission;
    }

    /**
     * @return array{vault_permission: string}
     */
    public function normalize(): array
    {
        return ['vault_permission' => $this->permission];
    }
}
