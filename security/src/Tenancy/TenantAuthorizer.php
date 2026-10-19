<?php

declare(strict_types=1);

namespace Gcego\Security\Tenancy;

use Gcego\Security\Audit\SecurityEvent;
use Gcego\Security\Audit\SecurityEventType;
use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Contracts\AuditReporter;
use Gcego\Security\Contracts\TenantResolver;
use Gcego\Security\Exceptions\TenantAccessException;
use Gcego\Security\SecurityConfig;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Session\Session;

/**
 * Turns an untrusted tenant key (route parameter, session, form field) into
 * a verified tenant context. A key proves nothing: the tenant must exist
 * and the authenticated user must hold a membership in it right now.
 * Unknown tenants and tenants without membership are indistinguishable (404 by default).
 */
final readonly class TenantAuthorizer
{
    public function __construct(
        private TenantResolver $resolver,
        private TenantContext $context,
        private AuditReporter $audit,
        private SecurityConfig $config,
    ) {}

    /**
     * @throws TenantAccessException
     */
    public function verify(?Authenticatable $user, string $key, string $guard = 'web'): TenantMembership
    {
        $tenant = self::isKey($key) ? $this->resolver->find($key) : null;
        $membership = $user === null || $tenant === null ? null : $this->resolver->membership($user, $tenant);

        if ($membership !== null && $tenant !== null && $membership->tenant->is($tenant) && $user !== null && (string) $membership->userKey === (string) AuthorizationContext::keyOf($user)) {
            return $membership;
        }

        $decision = AuthorizationDecision::deny('tenant.access', $user === null ? DenialReason::Unauthenticated : DenialReason::TenantDenied, null, $this->config->tenantDenialStrategy);

        if ($this->config->audit && $this->config->auditDenials) {
            $this->audit->report(SecurityEvent::fromDecision(SecurityEventType::TenantDenied, $decision, new AuthorizationContext($user, $guard)));
        }

        throw TenantAccessException::fromDecision($decision);
    }

    /**
     * Verify and make the tenant current for the rest of this request or job.
     *
     * @throws TenantAccessException
     */
    public function enter(?Authenticatable $user, string $key, string $guard = 'web'): TenantMembership
    {
        $membership = $this->verify($user, $key, $guard);
        $this->context->set($membership->tenant, $membership);

        return $membership;
    }

    /**
     * Switch tenants: verify, make current, and remember the choice in the session.
     * The stored key is verified again on every request (ResolveTenant).
     *
     * @throws TenantAccessException
     */
    public function switch(?Authenticatable $user, string $key, Session $session, string $guard = 'web'): TenantMembership
    {
        $membership = $this->enter($user, $key, $guard);
        $session->put($this->config->tenantSessionKey, (string) $membership->tenant->key);

        return $membership;
    }

    public function leave(?Session $session = null): void
    {
        $this->context->clear();
        $session?->forget($this->config->tenantSessionKey);
    }

    private static function isKey(string $key): bool
    {
        return $key !== '' && strlen($key) <= 100 && preg_match('/^[A-Za-z0-9_\-:.]+$/', $key) === 1;
    }
}
