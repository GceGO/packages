<?php

declare(strict_types=1);

namespace Gcego\Security;

use Closure;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Data\Definition\SourceDefinition;
use Gcego\Data\Source;
use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\AuthorizationEngine;
use Gcego\Security\Authorization\AuthorizerRegistry;
use Gcego\Security\Authorization\ContextFactory;
use Gcego\Security\Authorization\DenialStrategy;
use Gcego\Security\Contracts\Authorizer;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Contracts\ScopeProvider;
use Gcego\Security\Contracts\TenantResolver;
use Gcego\Security\Diagnostics\SecurityInspector;
use Gcego\Security\Diagnostics\SecurityReport;
use Gcego\Security\Exceptions\AccessDeniedException;
use Gcego\Security\Fields\FieldAuthorizer;
use Gcego\Security\Fields\FieldDecisions;
use Gcego\Security\Fields\FieldRule;
use Gcego\Security\Operations\OperationAuthorizer;
use Gcego\Security\Operations\SecuredOperation;
use Gcego\Security\Permissions\DecisionCache;
use Gcego\Security\Records\RecordAuthorizer;
use Gcego\Security\Resources\DefinitionAuthorizer;
use Gcego\Security\Resources\ResourceAbility;
use Gcego\Security\Resources\ResourceAccess;
use Gcego\Security\Roles\RoleResolver;
use Gcego\Security\Roles\RoleSet;
use Gcego\Security\Tenancy\TenantAuthorizer;
use Gcego\Security\Tenancy\TenantMembership;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Model;

/**
 * The entry point of gcego/security (no facade).
 *
 *     $security = app(SecurityManager::class);
 *
 *     $security->authorize('update', $subject)->authorize();       // Gate/policy, throws when denied
 *     $security->permission('subjects.view')->allowed;
 *     $security->check(new Permission('subjects.view'), Permission::inTenant('grades.update'));
 *     $security->record($source, $id, 'update');                   // scoped lookup + policy, 404/403
 *     $security->definition('blueprint:subjects');                  // a registered definition's requirements
 *
 * Every service it uses is resolved per call, so it is safe in long-running workers.
 */
final readonly class SecurityManager
{
    public function __construct(private Container $container) {}

    public function authorize(string $ability, mixed $resource = null, ?DenialStrategy $strategy = null): AuthorizationDecision
    {
        return $this->engine()->ability($ability, $resource, strategy: $strategy);
    }

    public function can(string $ability, mixed $resource = null): bool
    {
        return $this->authorize($ability, $resource)->allowed;
    }

    public function permission(string $permission, bool $tenant = false): AuthorizationDecision
    {
        return $this->engine()->permission($permission, tenant: $tenant);
    }

    public function check(Requirement ...$requirements): AuthorizationDecision
    {
        return $this->engine()->requirements($requirements);
    }

    /**
     * @param  list<Requirement>  $requirements
     */
    public function checkFor(array $requirements, mixed $resource): AuthorizationDecision
    {
        return $this->engine()->requirements($requirements, $resource);
    }

    /**
     * A definition instance, or a registered identity ("blueprint:subjects"), optionally at a component path.
     */
    public function definition(ComponentInterface|string $definition, ?string $path = null, mixed $resource = null): AuthorizationDecision
    {
        $definitions = $this->container->make(DefinitionAuthorizer::class);

        return is_string($definition) ? $definitions->identity($definition, $path, $resource) : $definitions->authorize($definition, $path, $resource);
    }

    /**
     * @param  iterable<mixed>  $records
     */
    public function bulk(ComponentInterface $definition, ?string $path, iterable $records): AuthorizationDecision
    {
        return $this->container->make(DefinitionAuthorizer::class)->bulk($definition, $path, $records);
    }

    public function resource(ResourceAccess $access, ResourceAbility|string $ability, ?Model $record = null): AuthorizationDecision
    {
        return $access->check($this->engine(), $this->context(), $ability, $record);
    }

    /**
     * @throws AccessDeniedException
     */
    public function record(Source|SourceDefinition $source, string|int $key, string $ability = 'view', ?DenialStrategy $strategy = null): mixed
    {
        return $this->container->make(RecordAuthorizer::class)->resolve($source, $key, $ability, $strategy);
    }

    /**
     * @param  list<string|FieldRule>  $fields
     */
    public function fields(array $fields, mixed $record = null): FieldDecisions
    {
        return $this->container->make(FieldAuthorizer::class)->decide($fields, $record);
    }

    public function context(): AuthorizationContext
    {
        return $this->container->make(ContextFactory::class)->current();
    }

    public function roles(): RoleSet
    {
        return $this->container->make(RoleResolver::class)->roles($this->context());
    }

    /**
     * Run as another user and/or tenant (jobs, commands, impersonation tooling), then restore.
     *
     * With a user, the tenant membership is verified now. Without one (system
     * work), the tenant is entered without a membership: scopes apply, but
     * tenant permissions are denied.
     *
     * @template T
     *
     * @param  Closure(AuthorizationContext): T  $callback
     * @return T
     */
    public function actingAs(?Authenticatable $user, ?string $tenant, Closure $callback, ?string $guard = null): mixed
    {
        $contexts = $this->container->make(ContextFactory::class);
        $guard ??= $contexts->guard();
        $resolvedTenant = null;
        $membership = null;

        if ($tenant !== null) {
            if ($user !== null) {
                $membership = $this->container->make(TenantAuthorizer::class)->verify($user, $tenant, $guard);
                $resolvedTenant = $membership->tenant;
            } else {
                $resolvedTenant = $this->container->make(TenantResolver::class)->find($tenant)
                    ?? throw Exceptions\TenantAccessException::fromDecision(AuthorizationDecision::deny('tenant.access', Authorization\DenialReason::TenantDenied, null, DenialStrategy::Conceal));
            }
        }

        $context = new AuthorizationContext($user, $guard, $resolvedTenant, $membership);

        return $contexts->run($context, static fn (): mixed => $callback($context));
    }

    public function enterTenant(string $key): TenantMembership
    {
        $context = $this->context();

        return $this->container->make(TenantAuthorizer::class)->enter($context->user, $key, $context->guard);
    }

    public function switchTenant(string $key, Session $session): TenantMembership
    {
        $context = $this->context();

        return $this->container->make(TenantAuthorizer::class)->switch($context->user, $key, $session, $context->guard);
    }

    public function operation(string $ability, mixed $resource = null, ?string $definition = null, ?string $path = null): SecuredOperation
    {
        return $this->container->make(OperationAuthorizer::class)->issue($ability, $resource, $definition, $path);
    }

    /**
     * @template T
     *
     * @param  Closure(AuthorizationContext, mixed): T  $callback
     * @return T
     */
    public function runOperation(SecuredOperation $operation, Closure $callback): mixed
    {
        return $this->container->make(OperationAuthorizer::class)->run($operation, $callback);
    }

    public function authorizeDownload(SecuredOperation $operation, string $path): void
    {
        $this->container->make(OperationAuthorizer::class)->authorizeDownload($operation, $path);
    }

    /**
     * Drop this request's memoized permission decisions, after changing roles or permissions mid-request.
     */
    public function forgetDecisions(): void
    {
        $this->container->make(DecisionCache::class)->flush();
    }

    public function extend(Authorizer $authorizer): void
    {
        $this->container->make(AuthorizerRegistry::class)->authorizer($authorizer);
    }

    public function scope(ScopeProvider $provider): void
    {
        $this->container->make(AuthorizerRegistry::class)->scope($provider);
    }

    public function inspect(): SecurityReport
    {
        return $this->container->make(SecurityInspector::class)->inspect();
    }

    private function engine(): AuthorizationEngine
    {
        return $this->container->make(AuthorizationEngine::class);
    }
}
