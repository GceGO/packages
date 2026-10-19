<?php

declare(strict_types=1);

namespace Gcego\Security\Integration;

use Gcego\Data\Contracts\DataAuthorizer;
use Gcego\Data\Definition\SourceDefinition;
use Gcego\Data\Query\QueryContext;
use Gcego\Data\Security\DataOperation;
use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\AuthorizationEngine;
use Gcego\Security\Authorization\ContextFactory;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\SecurityConfig;
use Gcego\Security\Tenancy\TenantScope;
use Illuminate\Contracts\Container\Container;

/**
 * gcego/data's DataAuthorizer, replacing its Gate-only default:
 *
 *  1. A tenant-owned source (tenancy.models) needs a selected tenant.
 *  2. Requirement nodes on the Source (Permission, ResourceAccess, ...) must allow,
 *     with the record for single-record reads.
 *  3. When the model has a policy, viewAny / view must allow as well.
 *  4. With neither requirements nor a policy, the read is denied.
 *
 * Resolved per call; holds no request state.
 */
final readonly class SecurityDataAuthorizer implements DataAuthorizer
{
    public function __construct(private Container $container) {}

    public function authorize(SourceDefinition $source, DataOperation $operation, QueryContext $context, mixed $record = null): void
    {
        $this->decide($source, $operation, $context, $record)->authorize();
    }

    public function decide(SourceDefinition $source, DataOperation $operation, QueryContext $context, mixed $record = null): AuthorizationDecision
    {
        $engine = $this->container->make(AuthorizationEngine::class);
        $contexts = $this->container->make(ContextFactory::class);
        $current = $contexts->current();
        $security = $context->user === null || ($current->user !== null && AuthorizationContext::keyOf($current->user) === AuthorizationContext::keyOf($context->user) && $current->user::class === $context->user::class)
            ? $current
            : $contexts->forUser($context->user);
        $resource = $operation === DataOperation::View ? $record : $source->model;
        $tenantScope = new TenantScope($this->container->make(SecurityConfig::class)->tenantModels);

        if ($tenantScope->column($source->model) !== null && $security->tenant === null) {
            return $engine->record(AuthorizationDecision::deny($operation->value, DenialReason::TenantRequired, $resource, $engine->strategy()), $security);
        }

        $requirements = array_values(array_filter($source->source->configurations()->all(), static fn (mixed $node): bool => $node instanceof Requirement));
        $hasPolicy = $source->model !== null && $engine->policyFor($source->model) !== null;

        if ($requirements === [] && ! $hasPolicy) {
            return $engine->record(AuthorizationDecision::deny($operation->value, $source->model === null ? DenialReason::NoRequirement : DenialReason::NoPolicy, $resource, $engine->strategy(), metadata: ['source' => $source->name]), $security);
        }

        if ($requirements !== []) {
            $decision = $engine->requirements($requirements, $resource, $security, $operation->value);

            if ($decision->denied()) {
                return $decision;
            }
        }

        return $hasPolicy ? $engine->ability($operation->value, $resource, $security) : AuthorizationDecision::allow($operation->value, $resource);
    }
}
