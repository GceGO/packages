<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Data\Definition\SourceDefinition;
use Gcego\Data\Source;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Exceptions\AccessDeniedException;
use Gcego\Security\Fields\FieldAccess;
use Gcego\Security\Fields\FieldAuthorizer;
use Gcego\Security\Fields\FieldRule;
use Gcego\Security\SecurityManager;
use Gcego\Testing\Assertions\Verifier;
use Gcego\Testing\Contracts\TestEnvironment;
use Gcego\Testing\Support\Reports;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Authorization as gcego/security decides it for the acting user: gates and
 * policies, requirements, registered definitions, records and fields.
 *
 * Every check asks the production SecurityManager; nothing is stubbed. A
 * passing "cannot" assertion proves the server refuses the operation, which
 * a hidden button never does.
 */
final class SecurityTestable extends Testable
{
    public function __construct(Verifier $verifier, private readonly Container $container, private readonly TestEnvironment $environment)
    {
        parent::__construct($verifier);
    }

    /**
     * Signs the user in (and enters the tenant) for the following checks.
     */
    public function actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): self
    {
        $this->environment->actingAs($user, $tenant, $guard);

        return $this;
    }

    public function decision(string $ability, mixed $resource = null): AuthorizationDecision
    {
        return $this->security()->authorize($ability, $resource);
    }

    public function assertCan(string $ability, mixed $resource = null): self
    {
        $decision = $this->decision($ability, $resource);

        return $this->check($decision->allowed, sprintf('assertCan(%s, %s)', $this->export($ability), $this->export($resource)), sprintf('Expected the acting user to be allowed to [%s].', $ability), ['allowed' => true], $this->decisionValues($decision));
    }

    public function assertCannot(string $ability, mixed $resource = null): self
    {
        $decision = $this->decision($ability, $resource);

        return $this->check(! $decision->allowed, sprintf('assertCannot(%s, %s)', $this->export($ability), $this->export($resource)), sprintf('Expected the acting user to be refused [%s].', $ability), ['allowed' => false], $this->decisionValues($decision), 'Check the policy or gate for this ability; gcego/security denies by default only when nothing grants it.');
    }

    /**
     * Every requirement allows the acting user.
     */
    public function assertAuthorized(Requirement ...$requirements): self
    {
        $decision = $this->security()->check(...$requirements);

        return $this->check($decision->allowed, 'assertAuthorized('.$this->requirements($requirements).')', 'Expected the requirements to allow the acting user.', ['allowed' => true], $this->decisionValues($decision));
    }

    /**
     * Some requirement refuses the acting user, with a 403 (and the reason,
     * when given).
     */
    public function assertForbidden(Requirement|AuthorizationDecision $requirement, ?DenialReason $reason = null): self
    {
        $decision = $requirement instanceof AuthorizationDecision ? $requirement : $this->security()->check($requirement);

        return $this->check($decision->denied() && $decision->status === 403 && ($reason === null || $decision->reason === $reason), 'assertForbidden('.($requirement instanceof Requirement ? $requirement->describe() : 'decision').')', 'Expected a 403 refusal'.($reason !== null ? ' ('.$reason->value.')' : '').'.', ['status' => 403, 'reason' => $reason], $this->decisionValues($decision));
    }

    /**
     * Refused as if the resource did not exist (404).
     */
    public function assertConcealed(Requirement|AuthorizationDecision $requirement): self
    {
        $decision = $requirement instanceof AuthorizationDecision ? $requirement : $this->security()->check($requirement);

        return $this->check($decision->denied() && $decision->status === 404, 'assertConcealed()', 'Expected a 404 refusal.', ['status' => 404], $this->decisionValues($decision));
    }

    /**
     * A registered definition ("blueprint:subjects", "page:reports"…), or a
     * path inside it, allows the acting user.
     */
    public function assertDefinitionAllowed(ComponentInterface|string $definition, ?string $path = null, mixed $resource = null): self
    {
        $decision = $this->security()->definition($definition, $path, $resource);

        return $this->check($decision->allowed, sprintf('assertDefinitionAllowed(%s, %s)', $this->name($definition), $this->export($path)), 'Expected the definition to allow the acting user.', ['allowed' => true], $this->decisionValues($decision));
    }

    public function assertDefinitionDenied(ComponentInterface|string $definition, ?string $path = null, mixed $resource = null, ?int $status = null): self
    {
        $decision = $this->security()->definition($definition, $path, $resource);

        return $this->check($decision->denied() && ($status === null || $decision->status === $status), sprintf('assertDefinitionDenied(%s, %s)', $this->name($definition), $this->export($path)), 'Expected the definition to refuse the acting user.', ['allowed' => false, 'status' => $status], $this->decisionValues($decision));
    }

    /**
     * The record is found through the source's scopes and the ability is
     * allowed on it.
     */
    public function assertRecordAccessible(Source|SourceDefinition $source, string|int $key, string $ability = 'view'): self
    {
        $exception = $this->caught(fn () => $this->security()->record($source, $key, $ability));

        return $this->check($exception === null, sprintf('assertRecordAccessible(%s, %s)', $this->export($key), $this->export($ability)), sprintf('Expected the record [%s] to be accessible.', $key), ['status' => 200], ['exception' => $this->describe($exception), 'status' => $this->statusOf($exception)]);
    }

    /**
     * The record is out of scope (another tenant, forged or missing key): 404.
     */
    public function assertRecordHidden(Source|SourceDefinition $source, string|int $key, string $ability = 'view'): self
    {
        $exception = $this->caught(fn () => $this->security()->record($source, $key, $ability));

        return $this->check($this->statusOf($exception) === 404, sprintf('assertRecordHidden(%s, %s)', $this->export($key), $this->export($ability)), sprintf('Expected the record [%s] to be reported as not found.', $key), ['status' => 404], ['status' => $this->statusOf($exception) ?? 200, 'exception' => $this->describe($exception)], 'Out-of-scope records must be indistinguishable from missing ones.');
    }

    /**
     * The record is in scope but the policy refuses the ability: 403.
     */
    public function assertRecordForbidden(Source|SourceDefinition $source, string|int $key, string $ability = 'view'): self
    {
        $exception = $this->caught(fn () => $this->security()->record($source, $key, $ability));

        return $this->check($this->statusOf($exception) === 403, sprintf('assertRecordForbidden(%s, %s)', $this->export($key), $this->export($ability)), sprintf('Expected [%s] on the record [%s] to be forbidden.', $ability, $key), ['status' => 403], ['status' => $this->statusOf($exception) ?? 200, 'exception' => $this->describe($exception)]);
    }

    /**
     * @param  list<string|FieldRule>  $rules
     */
    public function assertFieldAccess(array $rules, string $field, FieldAccess $access, mixed $record = null): self
    {
        $actual = $this->security()->fields($rules, $record)->access($field);

        return $this->check($actual === $access, sprintf('assertFieldAccess(%s, %s)', $this->export($field), $access->name), sprintf('Expected the field [%s] to be %s.', $field, $access->name), ['access' => $access->name], ['access' => $actual->name]);
    }

    /**
     * Submitting $input with these field rules is refused because it
     * contains a field the acting user may not write (or that is undeclared).
     *
     * @param  list<string|FieldRule>  $rules
     * @param  array<string, mixed>  $input
     */
    public function assertWriteRejected(array $rules, array $input, mixed $record = null): self
    {
        $exception = $this->caught(fn () => $this->container->make(FieldAuthorizer::class)->writable($input, $this->security()->fields($rules, $record)));

        return $this->check($exception instanceof AccessDeniedException, 'assertWriteRejected('.$this->export(array_keys($input)).')', 'Expected the submission to be refused.', ['exception' => AccessDeniedException::class], ['exception' => $this->describe($exception)]);
    }

    /**
     * A policy is registered for the model (class or instance).
     */
    public function assertPolicyApplied(string|object $model, ?string $policy = null): self
    {
        $actual = $this->container->make(Gate::class)->getPolicyFor($model);
        $actual = is_object($actual) ? $actual : null;

        return $this->check($actual !== null && ($policy === null || $actual instanceof $policy), sprintf('assertPolicyApplied(%s)', is_string($model) ? $model : $model::class), 'Expected a policy to be registered for the model.', ['policy' => $policy ?? '(any)'], ['policy' => $actual === null ? null : $actual::class], 'Register it with Gate::policy() or a #[UsePolicy] attribute.');
    }

    public function assertHealthy(): self
    {
        $report = $this->security()->inspect();

        return $this->check($report->isHealthy(), 'assertHealthy()', 'Expected the security inspector to report no errors.', ['errors' => []], ['errors' => Reports::problems($report)]);
    }

    protected function subject(): string
    {
        return 'security';
    }

    protected function context(): array
    {
        $user = $this->environment->user();

        return ['User' => $user === null ? 'guest' : $user->getAuthIdentifier(), 'Tenant' => $this->environment->currentTenant() ?? '(none)'];
    }

    private function security(): SecurityManager
    {
        return $this->container->make(SecurityManager::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function decisionValues(AuthorizationDecision $decision): array
    {
        return ['allowed' => $decision->allowed, 'status' => $decision->status, 'reason' => $decision->reason?->value, 'reason description' => $decision->reason?->describe(), 'resource' => $decision->resource->toString()];
    }

    /**
     * @param  array<Requirement>  $requirements
     */
    private function requirements(array $requirements): string
    {
        return implode(', ', array_map(static fn (Requirement $requirement): string => $requirement->describe(), $requirements));
    }

    private function name(ComponentInterface|string $definition): string
    {
        return is_string($definition) ? $definition : $definition::class;
    }

    private function statusOf(?Throwable $exception): ?int
    {
        return match (true) {
            $exception === null => null,
            $exception instanceof AccessDeniedException => $exception->status(),
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };
    }
}
