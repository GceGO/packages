<?php

declare(strict_types=1);

namespace Gcego\Security\Operations;

use Closure;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Security\Audit\SecurityEvent;
use Gcego\Security\Audit\SecurityEventType;
use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\AuthorizationEngine;
use Gcego\Security\Authorization\ContextFactory;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Authorization\DenialStrategy;
use Gcego\Security\Contracts\AuditReporter;
use Gcego\Security\Contracts\TenantResolver;
use Gcego\Security\Exceptions\AccessDeniedException;
use Gcego\Security\Resources\DefinitionAuthorizer;
use Gcego\Security\SecurityConfig;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Authorization for queued operations, exports and downloads:
 *
 *     $operation = $operations->issue('export', $subject);         // request time: checked now
 *     dispatch(new BuildExport($operation->toArray()));
 *
 *     // in the job:
 *     $operations->run(SecuredOperation::fromArray($payload), function (AuthorizationContext $context, ?Subject $subject) { ... });
 *
 *     // on download:
 *     $operations->authorizeDownload($operation, $path);
 *
 * run() reloads the user through the guard's provider, re-verifies the tenant
 * membership, reloads the record inside the tenant, and repeats the check,
 * so a permission revoked after dispatch stops the job. Nothing from the
 * request is trusted as a snapshot.
 */
final readonly class OperationAuthorizer
{
    public function __construct(
        private AuthorizationEngine $engine,
        private DefinitionAuthorizer $definitions,
        private ContextFactory $contexts,
        private TenantResolver $tenants,
        private AuthManager $auth,
        private AuditReporter $audit,
        private SecurityConfig $config,
        private ?RegistryInterface $registry = null,
    ) {}

    /**
     * @throws AccessDeniedException
     */
    public function issue(string $ability, mixed $resource = null, ?string $definition = null, ?string $path = null): SecuredOperation
    {
        $context = $this->contexts->current();
        $this->check($ability, $resource, $definition, $path, $context)->authorize();

        return new SecuredOperation(
            id: (string) Str::uuid(),
            ability: $ability,
            guard: $context->guard,
            userKey: $context->userKey(),
            tenant: $context->tenant?->id(),
            resourceType: $resource instanceof Model ? $resource::class : null,
            resourceKey: $resource instanceof Model ? self::key($resource) : null,
            definition: $definition,
            path: $path,
            issuedAt: time(),
        );
    }

    /**
     * Re-authorize with fresh state and run the work as the operation's user and tenant.
     *
     * @template T
     *
     * @param  Closure(AuthorizationContext, mixed): T  $callback
     * @return T
     *
     * @throws AccessDeniedException
     */
    public function run(SecuredOperation $operation, Closure $callback): mixed
    {
        $context = $this->restore($operation);

        return $this->contexts->run($context, function () use ($operation, $context, $callback): mixed {
            $resource = $this->resource($operation);
            $decision = $this->check($operation->ability, $resource, $operation->definition, $operation->path, $context);

            if ($decision->denied()) {
                $this->revoked($decision, $context);
                $decision->authorize();
            }

            return $callback($context, $resource);
        });
    }

    /**
     * A download of an operation's output: same user, same guard, same tenant,
     * a path inside the operation's tenant directory, and the original check
     * still allowing. Anything else is 404.
     *
     * @throws AccessDeniedException
     */
    public function authorizeDownload(SecuredOperation $operation, string $path): void
    {
        $current = $this->contexts->current($operation->guard);
        $sameUser = $current->user !== null && (string) $current->userKey() === (string) $operation->userKey;
        $sameTenant = ($this->contexts->current()->tenant?->id()) === $operation->tenant;

        if (! $sameUser || ! $sameTenant || ! TenantPath::contains($operation->tenant, $path) || ! str_starts_with($path, $operation->storagePrefix())) {
            AuthorizationDecision::deny('download', DenialReason::NotFound, null, DenialStrategy::Conceal)->authorize();
        }

        $this->run($operation, static fn (): bool => true);
    }

    private function restore(SecuredOperation $operation): AuthorizationContext
    {
        $user = null;

        if ($operation->userKey !== null) {
            $provider = $this->auth->guard($operation->guard)->getProvider();
            $user = $provider->retrieveById($operation->userKey);

            if (! $user instanceof Authenticatable) {
                $this->stale($operation);
            }
        }

        if ($operation->tenant === null) {
            return new AuthorizationContext($user, $operation->guard);
        }

        [, $key] = explode(':', $operation->tenant, 2) + [1 => ''];
        $tenant = $this->tenants->find($key);
        $membership = $tenant !== null && $user !== null ? $this->tenants->membership($user, $tenant) : null;

        if ($tenant === null || $tenant->id() !== $operation->tenant || ($user !== null && $membership === null)) {
            $this->stale($operation, DenialReason::TenantDenied);
        }

        return new AuthorizationContext($user, $operation->guard, $tenant, $membership);
    }

    private function resource(SecuredOperation $operation): mixed
    {
        if ($operation->resourceType === null || $operation->resourceKey === null) {
            return null;
        }

        $class = $operation->resourceType;

        if (! is_subclass_of($class, Model::class)) {
            $this->stale($operation);
        }

        // Reloaded inside the operation's tenant, so global scopes apply again.
        return $class::query()->whereKey($operation->resourceKey)->first() ?? $this->stale($operation, DenialReason::NotFound);
    }

    private function check(string $ability, mixed $resource, ?string $definition, ?string $path, AuthorizationContext $context): AuthorizationDecision
    {
        if ($definition === null) {
            return $this->engine->ability($ability, $resource, $context);
        }

        $component = $this->registry?->get($definition);

        return $component instanceof ComponentInterface
            ? $this->definitions->authorize($component, $path, $resource, $context, $ability)
            : AuthorizationDecision::deny($ability, DenialReason::NotFound, $resource, DenialStrategy::Conceal);
    }

    private function stale(SecuredOperation $operation, DenialReason $reason = DenialReason::Stale): never
    {
        $decision = AuthorizationDecision::deny($operation->ability, $reason, null, $this->config->denialStrategy, metadata: ['operation' => $operation->id]);
        $this->revoked($decision, new AuthorizationContext(null, $operation->guard));

        throw AccessDeniedException::fromDecision($decision);
    }

    private function revoked(AuthorizationDecision $decision, AuthorizationContext $context): void
    {
        if ($this->config->audit) {
            $this->audit->report(SecurityEvent::fromDecision(SecurityEventType::OperationRevoked, $decision, $context));
        }
    }

    private static function key(Model $model): string|int|null
    {
        $key = $model->getKey();

        return is_int($key) || is_string($key) ? $key : null;
    }
}
