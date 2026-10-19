<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Gcego\Data\DataManager;
use Gcego\Data\Definition\SourceDefinition;
use Gcego\Data\Exceptions\InvalidQueryException;
use Gcego\Data\Query\QueryContext;
use Gcego\Data\Query\QueryInput;
use Gcego\Data\Source;
use Gcego\Testing\Assertions\Verifier;
use Gcego\Testing\Contracts\TestEnvironment;
use Gcego\Testing\Support\Reports;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Queries a gcego/data source through DataManager, as the acting user
 * (policies and tenant scopes applied) or as the system (scopes only).
 */
final class DataTestable extends Testable
{
    private bool $system = false;

    public function __construct(Verifier $verifier, private readonly Container $container, private readonly TestEnvironment $environment, private readonly Source|SourceDefinition $source)
    {
        parent::__construct($verifier);
    }

    public function actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): self
    {
        $this->environment->actingAs($user, $tenant, $guard);
        $this->system = false;

        return $this;
    }

    /**
     * Queries without authorization (QueryContext::system()); tenant and
     * other scopes still apply.
     */
    public function asSystem(): self
    {
        $this->system = true;

        return $this;
    }

    /**
     * @param  QueryInput|array<string, mixed>  $input  Untrusted request input: filter, search, sort, page, per_page, cursor, include.
     */
    public function query(QueryInput|array $input = []): ResultTestable
    {
        $result = $this->manager()->query($this->source, $input, $this->queryContext());

        return new ResultTestable($this->verifier, $result, ['Input' => $input instanceof QueryInput ? $input->toArray() : $input]);
    }

    /**
     * The input is refused as invalid (forged filter, unknown sort field,
     * unsupported search…). Nothing is returned.
     *
     * @param  QueryInput|array<string, mixed>  $input
     * @param  class-string<Throwable>  $exception
     */
    public function assertRejects(QueryInput|array $input, string $exception = InvalidQueryException::class): self
    {
        $thrown = $this->caught(fn () => $this->manager()->query($this->source, $input, $this->queryContext()));

        return $this->check($thrown instanceof $exception, 'assertRejects('.$this->export($input instanceof QueryInput ? $input->toArray() : $input).')', 'Expected the query input to be refused.', ['exception' => $exception], ['exception' => $this->describe($thrown)]);
    }

    /**
     * Listing is refused for the acting user (the viewAny check).
     *
     * @param  QueryInput|array<string, mixed>  $input
     */
    public function assertDenied(QueryInput|array $input = []): self
    {
        $thrown = $this->caught(fn () => $this->manager()->query($this->source, $input, $this->queryContext()));

        return $this->check($thrown instanceof AuthorizationException, 'assertDenied()', 'Expected the acting user to be refused the listing.', ['exception' => AuthorizationException::class], ['exception' => $this->describe($thrown)]);
    }

    public function assertFinds(string|int $key): self
    {
        $found = false;
        $thrown = $this->caught(function () use ($key, &$found): void {
            $found = $this->manager()->find($this->source, $key, $this->queryContext())->found();
        });

        return $this->check($found, sprintf('assertFinds(%s)', $this->export($key)), sprintf('Expected the record [%s] to be found.', $key), ['found' => true], ['found' => false, 'exception' => $this->describe($thrown)]);
    }

    /**
     * The key resolves to nothing: missing, malformed, or outside the
     * acting user's scope (another tenant).
     */
    public function assertNotFound(string|int $key): self
    {
        $found = false;
        $thrown = $this->caught(function () use ($key, &$found): void {
            $found = $this->manager()->find($this->source, $key, $this->queryContext())->found();
        });

        return $this->check(! $found && ($thrown === null || $thrown instanceof AuthorizationException), sprintf('assertNotFound(%s)', $this->export($key)), sprintf('Expected the record [%s] not to be found.', $key), ['found' => false], ['found' => $found, 'exception' => $this->describe($thrown)]);
    }

    public function assertHealthy(): self
    {
        $problems = Reports::problems($this->manager()->inspect($this->source));

        return $this->check($problems === [], 'assertHealthy()', 'Expected the data inspector to report no errors.', ['errors' => []], ['errors' => $problems]);
    }

    protected function subject(): string
    {
        return 'data';
    }

    protected function context(): array
    {
        return ['Source' => $this->manager()->definition($this->source)->name, 'Context' => $this->system ? 'system' : 'acting user'];
    }

    private function queryContext(): QueryContext
    {
        return $this->system ? QueryContext::system() : QueryContext::for($this->environment->user());
    }

    private function manager(): DataManager
    {
        return $this->container->make(DataManager::class);
    }
}
