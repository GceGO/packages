<?php

declare(strict_types=1);

namespace Gcego\Testing\Environment;

use Closure;
use Gcego\Panels\Context\PanelContextResolver;
use Gcego\Panels\PanelManager;
use Gcego\Registry\Registry;
use Gcego\Security\Permissions\DecisionCache;
use Gcego\Security\SecurityManager;
use Gcego\Security\Tenancy\TenantContext;
use Gcego\Testing\Fixtures\FixtureManager;
use Illuminate\Contracts\Container\Container;

/**
 * Clears what one test can leave behind for the next inside the same
 * application instance (Laravel normally boots a fresh application per
 * test; long-running processes and suites that reuse one need this).
 *
 * Reset: signed-in user (every guard), tenant context, panel context,
 * security decision cache, per-request services, Livewire component state,
 * resolved registry definitions (runtime registrations are kept by the
 * registry itself), the fixtures' workspaces, and any callbacks added with
 * using().
 */
final class TestStateResetter
{
    /** @var list<Closure(Container): void> */
    private array $resetters = [];

    /**
     * @param  array{reset_registry?: bool, reset_runtime?: bool, reset_tenant_context?: bool}  $options
     */
    public function __construct(private readonly Container $container, private readonly array $options = []) {}

    /**
     * @param  Closure(Container): void  $resetter
     */
    public function using(Closure $resetter): self
    {
        $this->resetters[] = $resetter;

        return $this;
    }

    public function reset(): void
    {
        if ($this->options['reset_tenant_context'] ?? true) {
            $this->resetTenant();
        }

        if ($this->container->bound('auth')) {
            $this->container->make('auth')->forgetGuards();
        }

        if (class_exists(PanelContextResolver::class) && $this->container->bound(PanelManager::class)) {
            $this->container->make(PanelContextResolver::class)->clear();
        }

        if (($this->options['reset_runtime'] ?? true) && $this->container->bound('livewire')) {
            $livewire = $this->container->make('livewire');

            if (is_object($livewire) && method_exists($livewire, 'flushState')) {
                $livewire->flushState();
            }
        }

        if (($this->options['reset_registry'] ?? true) && class_exists(Registry::class) && $this->container->bound(Registry::class)) {
            $this->container->make(Registry::class)->refresh();
        }

        foreach ($this->resetters as $resetter) {
            $resetter($this->container);
        }

        if ($this->container->bound(FixtureManager::class)) {
            $this->container->make(FixtureManager::class)->cleanup();
        }

        if (method_exists($this->container, 'forgetScopedInstances')) {
            $this->container->forgetScopedInstances();
        }
    }

    private function resetTenant(): void
    {
        if (! class_exists(TenantContext::class) || ! $this->container->bound(SecurityManager::class)) {
            return;
        }

        $this->container->make(TenantContext::class)->clear();
        $this->container->make(DecisionCache::class)->flush();
    }
}
