<?php

declare(strict_types=1);

namespace Gcego\Security\Permissions;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Authorization\DenialStrategy;
use Gcego\Security\Contracts\PermissionProvider;

/**
 * Resolves one permission for a context.
 *
 * - Global permissions: the configured PermissionProvider (Gate or Spatie).
 * - Tenant permissions: only the verified TenantMembership of the active
 *   tenant. Global roles and Gate::before rules play no part, so a
 *   platform role never grants authority inside a tenant, and a role in one
 *   tenant grants nothing in another.
 *
 * Guests, unknown permissions and missing memberships are denied.
 */
final readonly class PermissionResolver
{
    public function __construct(
        private PermissionProvider $provider,
        private DecisionCache $cache,
        private DenialStrategy $strategy = DenialStrategy::Forbidden,
    ) {}

    public function check(AuthorizationContext $context, string $permission, bool $tenant = false): AuthorizationDecision
    {
        PermissionName::assert($permission);

        return $this->cache->remember($context->fingerprint().'|'.($tenant ? 't' : 'g').'|'.$permission, fn (): AuthorizationDecision => $this->resolve($context, $permission, $tenant));
    }

    private function resolve(AuthorizationContext $context, string $permission, bool $tenant): AuthorizationDecision
    {
        $metadata = ['permission' => $permission, 'scope' => $tenant ? 'tenant' : 'global'];

        if ($context->user === null) {
            return AuthorizationDecision::deny($permission, DenialReason::Unauthenticated, status: $this->strategy, metadata: $metadata);
        }

        if ($tenant) {
            return match (true) {
                $context->tenant === null => AuthorizationDecision::deny($permission, DenialReason::TenantRequired, status: $this->strategy, metadata: $metadata),
                $context->membership === null => AuthorizationDecision::deny($permission, DenialReason::TenantDenied, status: $this->strategy, metadata: $metadata),
                $context->membership->grants($permission) => AuthorizationDecision::allow($permission, metadata: $metadata),
                default => AuthorizationDecision::deny($permission, DenialReason::MissingPermission, status: $this->strategy, metadata: $metadata),
            };
        }

        return match ($this->provider->allows($context->user, $permission, $context)) {
            true => AuthorizationDecision::allow($permission, metadata: $metadata),
            false => AuthorizationDecision::deny($permission, DenialReason::MissingPermission, status: $this->strategy, metadata: $metadata),
            null => AuthorizationDecision::deny($permission, DenialReason::UnknownPermission, status: $this->strategy, metadata: $metadata),
        };
    }
}
