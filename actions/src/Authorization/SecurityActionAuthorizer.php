<?php

declare(strict_types=1);

namespace Gcego\Actions\Authorization;

use Gcego\Actions\Contracts\ActionAuthorizer;
use Gcego\Actions\Resolution\ResolvedAction;
use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\AuthorizationEngine;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Resources\DefinitionAuthorizer;
use Gcego\Security\SecurityConfig;
use Gcego\Security\Tenancy\TenantScope;
use Illuminate\Contracts\Container\Container;

/**
 * Authorizes an action through gcego/security, failing closed:
 *
 *  1. The definition chain: requirement nodes on the blueprint, any group and
 *     the action itself (DefinitionAuthorizer). None at all is a denial.
 *  2. The action must carry its own authorization: an Ability (built-ins
 *     have one) or requirement nodes. A custom action without either is
 *     denied, so declaring an action never makes it executable.
 *  3. The ability: the model's policy or a Gate ability, for the record
 *     (record actions, and every selected record of bulk actions) or the
 *     model class (create).
 *  4. Tenant-owned models (gcego-security.tenancy.models) need a selected tenant.
 *
 * Resolved per call; holds no request state.
 */
final readonly class SecurityActionAuthorizer implements ActionAuthorizer
{
    public function __construct(private Container $container) {}

    public function authorize(ResolvedAction $action, AuthorizationContext $context, mixed $record = null): AuthorizationDecision
    {
        $engine = $this->container->make(AuthorizationEngine::class);
        $resource = $record ?? $action->model();
        $ability = $action->action->ability();
        $own = array_filter($action->action->configurations()->all(), static fn (mixed $node): bool => $node instanceof Requirement);

        if ($ability === null && $own === []) {
            return AuthorizationDecision::deny($action->name(), DenialReason::NoRequirement, $resource, $engine->strategy(), metadata: ['action' => $action->identity()]);
        }

        if ((new TenantScope($this->container->make(SecurityConfig::class)->tenantModels))->column($action->model()) !== null && $context->tenant === null) {
            return AuthorizationDecision::deny($action->name(), DenialReason::TenantRequired, $resource, $engine->strategy());
        }

        $decision = $this->container->make(DefinitionAuthorizer::class)->authorize($action->blueprint, $action->path, $resource, $context, $action->name());

        // A bulk action's ability is a record ability: it is checked for every selected record, not for the class.
        if ($decision->denied() || $ability === null || ($action->isBulk() && $record === null)) {
            return $decision;
        }

        return $engine->ability($ability, $resource, $context);
    }
}
