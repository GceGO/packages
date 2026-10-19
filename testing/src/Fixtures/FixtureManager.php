<?php

declare(strict_types=1);

namespace Gcego\Testing\Fixtures;

use Closure;
use Gcego\Testing\Contracts\TenantFixtureProvider;
use Gcego\Testing\Contracts\UserFixtureProvider;
use Gcego\Testing\Exceptions\InvalidFixtureException;
use Gcego\Testing\Factories\TestTenantFactory;
use Gcego\Testing\Factories\TestUserFactory;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Creates and tracks a test's fixtures: temporary workspaces, registry
 * fixtures, representative definitions, users and tenants. cleanup()
 * removes every workspace and runs every registered cleanup callback, in
 * reverse order, even when one of them fails.
 *
 * Database rows are not tracked: use RefreshDatabase or
 * DatabaseTransactions, which already isolate them.
 */
final class FixtureManager
{
    /** @var list<Closure(): void> */
    private array $cleanups = [];

    /** @var list<Workspace> */
    private array $workspaces = [];

    private ?TestUserFactory $users = null;

    public function __construct(private readonly Container $container, private readonly ?string $directory = null, private readonly ?string $userProvider = null, private readonly ?string $tenantProvider = null) {}

    public function workspace(): Workspace
    {
        $workspace = Workspace::create($this->directory);
        $this->workspaces[] = $workspace;

        return $workspace;
    }

    public function registry(): RegistryFixture
    {
        return new RegistryFixture($this->workspace());
    }

    public function definitions(): DefinitionFactory
    {
        return new DefinitionFactory;
    }

    public function users(): TestUserFactory
    {
        return $this->users ??= new TestUserFactory($this->container, $this->resolve($this->userProvider, UserFixtureProvider::class, 'users'));
    }

    public function tenants(): TestTenantFactory
    {
        return new TestTenantFactory($this->resolve($this->tenantProvider, TenantFixtureProvider::class, 'tenants'));
    }

    /**
     * @param  Closure(): void  $cleanup
     */
    public function onCleanup(Closure $cleanup): void
    {
        $this->cleanups[] = $cleanup;
    }

    /**
     * @return list<Workspace>
     */
    public function workspaces(): array
    {
        return $this->workspaces;
    }

    public function cleanup(): void
    {
        $failure = null;

        foreach (array_reverse($this->cleanups) as $cleanup) {
            try {
                $cleanup();
            } catch (Throwable $exception) {
                $failure ??= $exception;
            }
        }

        foreach ($this->workspaces as $workspace) {
            $workspace->remove();
        }

        $this->cleanups = [];
        $this->workspaces = [];
        $this->users = null;

        if ($failure !== null) {
            throw $failure;
        }
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $contract
     * @return T|null
     */
    private function resolve(?string $class, string $contract, string $name): ?object
    {
        if ($class === null) {
            $bound = $this->container->bound($contract) ? $this->container->make($contract) : null;

            return $bound instanceof $contract ? $bound : null;
        }

        $provider = $this->container->make($class);

        if (! is_object($provider) || ! $provider instanceof $contract) {
            throw InvalidFixtureException::invalid($name, sprintf('provider %s must implement %s', $class, $contract));
        }

        return $provider;
    }
}
