<?php

declare(strict_types=1);

namespace Gcego\Security\Authorization;

use Gcego\Security\Audit\SecurityEvent;
use Gcego\Security\Audit\SecurityEventType;
use Gcego\Security\Contracts\AuditReporter;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Permissions\PermissionName;
use Gcego\Security\Permissions\PermissionResolver;
use Gcego\Security\SecurityConfig;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Orchestrates Laravel's authorization; it does not replace it.
 *
 * - ability(): registered custom Authorizers, then Gate::forUser($user)->inspect()
 *   (policies, Gate::define abilities and the application's before/after rules).
 * - permission(): PermissionResolver (global provider, or tenant membership).
 * - requirements(): every requirement must allow; none at all is a denial.
 *
 * Nothing is granted implicitly, and this class adds no bypass of its own.
 */
final class AuthorizationEngine
{
    public function __construct(
        private readonly Gate $gate,
        private readonly AuthorizerRegistry $registry,
        private readonly PermissionResolver $permissions,
        private readonly ContextFactory $contexts,
        private readonly AuditReporter $audit,
        private readonly SecurityConfig $config,
        private readonly ?Container $container = null,
    ) {}

    /**
     * The current request's session, for requirements such as PasswordConfirmed.
     */
    public function session(): ?Session
    {
        $request = $this->container?->bound('request') ? $this->container->make('request') : null;

        return $request instanceof Request && $request->hasSession() ? $request->session() : null;
    }

    public function context(): AuthorizationContext
    {
        return $this->contexts->current();
    }

    /**
     * A Gate ability or policy method, for a record, a class or nothing.
     */
    public function ability(string $ability, mixed $resource = null, ?AuthorizationContext $context = null, ?DenialStrategy $strategy = null): AuthorizationDecision
    {
        PermissionName::assert($ability, 'ability');
        $context ??= $this->context();
        $strategy ??= $this->config->denialStrategy;

        foreach ($this->registry->authorizers() as $authorizer) {
            $decision = $authorizer->decide($ability, $resource, $context);

            if ($decision !== null) {
                return $this->record($decision, $context);
            }
        }

        $arguments = $resource === null ? [] : [$resource];
        $defined = $this->gate->has($ability) || $this->policyFor($resource) !== null;
        $response = $this->gate->forUser($context->user)->inspect($ability, $arguments);
        $reason = match (true) {
            $response->allowed() => null,
            $context->user === null => DenialReason::Unauthenticated,
            ! $defined => DenialReason::NoPolicy,
            default => DenialReason::PolicyDenied,
        };

        return $this->record(AuthorizationDecision::fromResponse($response, $ability, $resource, $reason ?? DenialReason::PolicyDenied, $strategy), $context);
    }

    public function permission(string $permission, ?AuthorizationContext $context = null, bool $tenant = false): AuthorizationDecision
    {
        $context ??= $this->context();

        return $this->record($this->permissions->check($context, $permission, $tenant), $context);
    }

    /**
     * @param  iterable<Requirement>  $requirements
     */
    public function requirements(iterable $requirements, mixed $resource = null, ?AuthorizationContext $context = null, string $ability = 'access'): AuthorizationDecision
    {
        $context ??= $this->context();
        $evaluated = [];

        foreach ($requirements as $requirement) {
            $decision = $requirement->evaluate($this, $context, $resource);
            $evaluated[] = $requirement->describe();

            if ($decision->denied()) {
                return $decision;
            }
        }

        if ($evaluated === []) {
            return $this->record(AuthorizationDecision::deny($ability, DenialReason::NoRequirement, $resource, $this->config->denialStrategy), $context);
        }

        return AuthorizationDecision::allow($ability, $resource, ['requirements' => $evaluated]);
    }

    public function strategy(): DenialStrategy
    {
        return $this->config->denialStrategy;
    }

    public function policyFor(mixed $resource): mixed
    {
        if (! ($resource instanceof Model || (is_string($resource) && class_exists($resource)))) {
            return null;
        }

        return $this->gate->getPolicyFor($resource);
    }

    /**
     * Report the decision to the audit trail when configured, and return it.
     */
    public function record(AuthorizationDecision $decision, AuthorizationContext $context): AuthorizationDecision
    {
        if (! $this->config->audit) {
            return $decision;
        }

        if ($decision->denied() && $this->config->auditDenials) {
            $type = in_array($decision->reason, [DenialReason::TenantDenied, DenialReason::TenantRequired], true) ? SecurityEventType::TenantDenied : SecurityEventType::Denied;
            $this->audit->report(SecurityEvent::fromDecision($type, $decision, $context));
        } elseif ($decision->allowed) {
            foreach ($this->config->auditSensitive as $pattern) {
                if (fnmatch($pattern, $decision->ability)) {
                    $this->audit->report(SecurityEvent::fromDecision(SecurityEventType::SensitiveAllowed, $decision, $context));

                    break;
                }
            }
        }

        return $decision;
    }
}
