<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Closure;
use Gcego\Actions\ActionManager;
use Gcego\Actions\Execution\ActionRequest;
use Gcego\Actions\Execution\ActionStatus;
use Gcego\Data\DataManager;
use Gcego\Data\Definition\SourceDefinition;
use Gcego\Data\Query\QueryContext;
use Gcego\Data\Source;
use Gcego\Security\Exceptions\TenantAccessException;
use Gcego\Security\SecurityManager;
use Gcego\Security\Tenancy\TenantAuthorizer;
use Gcego\Testing\Assertions\Verifier;
use Gcego\Testing\Contracts\TestEnvironment;
use Gcego\Testing\Fixtures\TenantFixture;
use Gcego\Testing\Support\Records;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * Multi-tenant isolation from the point of view of one tenant.
 *
 * actingAs() signs the user in and enters this tenant through
 * gcego/security (membership is verified). The isolation assertions then
 * try the production paths with another tenant's identifiers and pass only
 * when the server refuses or finds nothing.
 */
final class TenantTestable extends Testable
{
    private readonly string $tenant;

    public function __construct(Verifier $verifier, private readonly Container $container, private readonly TestEnvironment $environment, string|int|TenantFixture $tenant)
    {
        parent::__construct($verifier);
        $this->tenant = (string) ($tenant instanceof TenantFixture ? $tenant->key : $tenant);
    }

    public function key(): string
    {
        return $this->tenant;
    }

    /**
     * Signs the user in and enters this tenant. Throws gcego/security's
     * TenantAccessException when the user is not a member.
     */
    public function actingAs(Authenticatable $user, ?string $guard = null): self
    {
        $this->environment->actingAs($user, $this->tenant, $guard);

        return $this;
    }

    /**
     * Runs the callback as the user inside this tenant and restores the
     * previous user and tenant afterwards, even when it throws.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(Authenticatable $user, Closure $callback): mixed
    {
        return $this->container->make(SecurityManager::class)->actingAs($user, $this->tenant, static fn (): mixed => $callback());
    }

    public function assertCurrent(): self
    {
        $current = $this->environment->currentTenant();

        return $this->check($current === $this->tenant, 'assertCurrent()', sprintf('Expected the current tenant to be [%s].', $this->tenant), ['tenant' => $this->tenant], ['tenant' => $current]);
    }

    /**
     * The acting user (or $user) is a member of this tenant (or $tenant).
     */
    public function assertCanAccessTenant(string|int|TenantFixture|null $tenant = null, ?Authenticatable $user = null): self
    {
        $key = $this->keyOf($tenant ?? $this->tenant);
        $exception = $this->caught(fn () => $this->authorizer()->verify($user ?? $this->environment->user(), $key, $this->guard()));

        return $this->check($exception === null, sprintf('assertCanAccessTenant(%s)', $this->export($key)), sprintf('Expected access to the tenant [%s].', $key), ['access' => true], ['exception' => $this->describe($exception)], 'Check the membership your TenantResolver returns for this user.');
    }

    /**
     * Entering the other tenant is refused for the acting user (or $user).
     * The current tenant is not changed.
     */
    public function assertCannotAccessTenant(string|int|TenantFixture $tenant, ?Authenticatable $user = null): self
    {
        $key = $this->keyOf($tenant);
        $exception = $this->caught(fn () => $this->authorizer()->verify($user ?? $this->environment->user(), $key, $this->guard()));

        return $this->check($exception instanceof TenantAccessException, sprintf('assertCannotAccessTenant(%s)', $this->export($key)), sprintf('Expected the tenant [%s] to be refused.', $key), ['exception' => TenantAccessException::class], ['exception' => $this->describe($exception)]);
    }

    /**
     * The source returns these records to the acting user.
     *
     * @param  iterable<string|int|Model>  $keys
     */
    public function assertSees(Source|SourceDefinition $source, iterable $keys): self
    {
        $visible = $this->visibleKeys($source);
        $missing = array_values(array_diff(Records::normalize($keys), $visible));

        return $this->check($missing === [], 'assertSees('.$this->export(Records::normalize($keys)).')', 'Expected the acting user to see these records in this tenant.', ['visible' => Records::normalize($keys)], ['visible' => $visible, 'missing' => $missing]);
    }

    /**
     * None of these records (typically another tenant's) is listed or can
     * be found by key.
     *
     * @param  iterable<string|int|Model>  $keys
     */
    public function assertCannotSee(Source|SourceDefinition $source, iterable $keys): self
    {
        $keys = Records::normalize($keys);
        $leaked = array_values(array_intersect($keys, $this->visibleKeys($source)));

        foreach ($keys as $key) {
            if ($this->data()->find($source, $key, QueryContext::for($this->environment->user()))->found()) {
                $leaked[] = $key.' (by key)';
            }
        }

        return $this->check($leaked === [], 'assertCannotSee('.$this->export($keys).')', 'Expected these records to be invisible in this tenant.', ['leaked' => []], ['leaked' => array_values(array_unique($leaked))], 'Map the model to its tenant column in gcego-security.tenancy.models, or scope its Source.');
    }

    /**
     * Running the action on another tenant's record is refused (not found
     * or denied) by the production action pipeline. If the isolation is
     * broken the action really runs, which is the failure being reported.
     *
     * @param  array<string, mixed>  $input
     */
    public function assertActionRefused(string $definition, string $action, string|int $record, array $input = []): self
    {
        $result = $this->actions()->execute(new ActionRequest($definition, $action, $record, input: $input));

        return $this->check(in_array($result->status, [ActionStatus::NotFound, ActionStatus::Denied], true), sprintf('assertActionRefused(%s, %s, %s)', $this->export($definition), $this->export($action), $this->export($record)), 'Expected the action on a foreign record to be refused.', ['status' => 'not_found|denied'], ['status' => $result->status->value, 'reason' => $result->reason, 'message' => $result->message]);
    }

    /**
     * A bulk selection made only of foreign keys processes nothing.
     *
     * @param  list<string|int>  $keys
     */
    public function assertSelectionRefused(string $definition, string $action, array $keys): self
    {
        $result = $this->actions()->execute(new ActionRequest($definition, $action, selection: $keys));
        $processed = is_int($result->data['processed'] ?? null) ? $result->data['processed'] : 0;

        return $this->check(! $result->successful() && $processed === 0, sprintf('assertSelectionRefused(%s, %s, %s)', $this->export($definition), $this->export($action), $this->export($keys)), 'Expected a selection of foreign records to be refused.', ['successful' => false, 'processed' => 0], ['status' => $result->status->value, 'reason' => $result->reason, 'processed' => $processed]);
    }

    /**
     * The callback (a job, a listener, a command) leaves the user and the
     * tenant as they were.
     */
    public function assertRestoresContext(Closure $callback): self
    {
        $before = [$this->environment->user()?->getAuthIdentifier(), $this->environment->currentTenant()];
        $callback();
        $after = [$this->environment->user()?->getAuthIdentifier(), $this->environment->currentTenant()];

        return $this->check($before === $after, 'assertRestoresContext()', 'Expected the user and tenant to be restored after the callback.', ['user' => $before[0], 'tenant' => $before[1]], ['user' => $after[0], 'tenant' => $after[1]]);
    }

    /**
     * No tenant is selected (after leaving, or in a new request).
     */
    public function assertCleared(): self
    {
        $current = $this->environment->currentTenant();

        return $this->check($current === null, 'assertCleared()', 'Expected no tenant to be selected.', ['tenant' => null], ['tenant' => $current]);
    }

    protected function subject(): string
    {
        return 'tenant';
    }

    protected function context(): array
    {
        $user = $this->environment->user();

        return ['Tenant' => $this->tenant, 'Current tenant' => $this->environment->currentTenant() ?? '(none)', 'User' => $user === null ? 'guest' : $user->getAuthIdentifier()];
    }

    /**
     * @return list<string>
     */
    private function visibleKeys(Source|SourceDefinition $source): array
    {
        $rows = $this->data()->scopedQuery($source, [], QueryContext::for($this->environment->user()))->get();

        return Records::keys($rows);
    }

    private function keyOf(string|int|TenantFixture $tenant): string
    {
        return (string) ($tenant instanceof TenantFixture ? $tenant->key : $tenant);
    }

    private function guard(): string
    {
        $guard = $this->container->make('config')->get('auth.defaults.guard');

        return is_string($guard) ? $guard : 'web';
    }

    private function authorizer(): TenantAuthorizer
    {
        return $this->container->make(TenantAuthorizer::class);
    }

    private function data(): DataManager
    {
        return $this->container->make(DataManager::class);
    }

    private function actions(): ActionManager
    {
        return $this->container->make(ActionManager::class);
    }
}
