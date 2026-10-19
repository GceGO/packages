<?php

declare(strict_types=1);

namespace Gcego\Security\Requirements;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\AuthorizationEngine;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Contracts\Requirement;

/**
 * Requires a selected tenant and the user's verified membership in it
 * (optionally with one of these tenant roles).
 */
final readonly class TenantMember implements Requirement
{
    /**
     * @var list<string>
     */
    public array $roles;

    public function __construct(string ...$roles)
    {
        $this->roles = array_values($roles);
    }

    public function evaluate(AuthorizationEngine $engine, AuthorizationContext $context, mixed $resource = null): AuthorizationDecision
    {
        $membership = $context->membership;

        return match (true) {
            $context->user === null => AuthorizationDecision::deny('tenant.member', DenialReason::Unauthenticated, $resource, $engine->strategy()),
            $context->tenant === null => AuthorizationDecision::deny('tenant.member', DenialReason::TenantRequired, $resource, $engine->strategy()),
            $membership === null => AuthorizationDecision::deny('tenant.member', DenialReason::TenantDenied, $resource, $engine->strategy()),
            $this->roles !== [] && array_intersect($this->roles, $membership->roles) === [] => AuthorizationDecision::deny('tenant.member', DenialReason::MissingPermission, $resource, $engine->strategy(), metadata: ['roles' => $this->roles]),
            default => AuthorizationDecision::allow('tenant.member', $resource),
        };
    }

    public function describe(): string
    {
        return 'tenant member'.($this->roles === [] ? '' : ' with role '.implode(' or ', $this->roles));
    }

    /**
     * @return array{roles: list<string>}
     */
    public function normalize(): array
    {
        return ['roles' => $this->roles];
    }
}
