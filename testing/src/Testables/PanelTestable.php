<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Gcego\Panels\Access\PanelAccess;
use Gcego\Panels\Access\PanelAccessResult;
use Gcego\Panels\Context\PanelContext;
use Gcego\Panels\PanelManager;
use Gcego\Panels\PanelsConfig;
use Gcego\Panels\Registry\PanelDescriptor;
use Gcego\Security\Tenancy\TenantContext;
use Gcego\Testing\Assertions\Verifier;
use Gcego\Testing\Contracts\TestEnvironment;
use Gcego\Testing\Support\Reports;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\Router;
use LogicException;

/**
 * A gcego/panels panel: registration, routes, membership of modules and
 * pages, and whether the acting user may enter it (PanelAccess, the same
 * decision the gcego.panel middleware makes on every request and Livewire
 * update).
 */
final class PanelTestable extends Testable
{
    public function __construct(Verifier $verifier, private readonly Container $container, private readonly TestEnvironment $environment, private readonly string $panel)
    {
        parent::__construct($verifier);
    }

    public function actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): self
    {
        $this->environment->actingAs($user, $tenant, $guard);

        return $this;
    }

    public function descriptor(): PanelDescriptor
    {
        return $this->manager()->descriptor($this->panel);
    }

    public function assertRegistered(): self
    {
        return $this->check($this->manager()->has($this->panel), 'assertRegistered()', sprintf('Expected the panel [%s] to be registered.', $this->panel), ['registered' => true], ['panels' => $this->manager()->names()]);
    }

    public function assertPath(string $path): self
    {
        return $this->check($this->descriptor()->path === $path, sprintf('assertPath(%s)', $this->export($path)), 'Expected the panel path.', ['path' => $path], ['path' => $this->descriptor()->path]);
    }

    public function assertHasModule(string $module): self
    {
        return $this->check(in_array($module, $this->descriptor()->modules, true), sprintf('assertHasModule(%s)', $this->export($module)), sprintf('Expected the panel to include the module [%s].', $module), ['module' => $module], ['modules' => $this->descriptor()->modules]);
    }

    public function assertHasPage(string $page): self
    {
        return $this->check(in_array($page, $this->descriptor()->pages, true), sprintf('assertHasPage(%s)', $this->export($page)), sprintf('Expected the panel to include the page [%s].', $page), ['page' => $page], ['pages' => $this->descriptor()->pages]);
    }

    /**
     * "required", "optional" or null (no tenant).
     */
    public function assertTenancy(?string $mode): self
    {
        return $this->check($this->descriptor()->tenant === $mode, sprintf('assertTenancy(%s)', $this->export($mode)), 'Expected the panel tenancy mode.', ['tenant' => $mode], ['tenant' => $this->descriptor()->tenant]);
    }

    /**
     * A named route exists, relative to the panel's route namespace
     * ("home", "academics.subjects.index").
     */
    public function assertRoute(string $name): self
    {
        $full = $this->descriptor()->routeNamespace($this->container->make(PanelsConfig::class)->namePrefix).$name;

        return $this->check($this->container->make(Router::class)->has($full), sprintf('assertRoute(%s)', $this->export($name)), sprintf('Expected the route [%s].', $full), ['route' => $full], ['registered' => false], 'Panel routes are registered when the application boots; a panel registered at runtime needs PanelRouteRegistrar::registerPanel().');
    }

    public function assertAccessible(): self
    {
        $result = $this->decide();

        return $this->check($result === PanelAccessResult::Allowed, 'assertAccessible()', 'Expected the acting user to be allowed into the panel.', ['access' => PanelAccessResult::Allowed->name], ['access' => $result->name]);
    }

    public function assertInaccessible(?PanelAccessResult $reason = null): self
    {
        $result = $this->decide();

        return $this->check($result !== PanelAccessResult::Allowed && ($reason === null || $result === $reason), 'assertInaccessible('.($reason === null ? '' : $reason->name).')', 'Expected the acting user to be refused the panel.', ['access' => $reason->name ?? 'not Allowed'], ['access' => $result->name]);
    }

    public function assertHealthy(): self
    {
        $problems = Reports::problems($this->manager()->inspect($this->panel));

        return $this->check($problems === [], 'assertHealthy()', 'Expected the panel inspector to report no errors.', ['errors' => []], ['errors' => $problems]);
    }

    protected function subject(): string
    {
        return 'panel';
    }

    protected function context(): array
    {
        $user = $this->environment->user();

        return ['Panel' => $this->panel, 'User' => $user === null ? 'guest' : $user->getAuthIdentifier(), 'Tenant' => $this->environment->currentTenant() ?? '(none)'];
    }

    private function decide(): PanelAccessResult
    {
        $user = $this->environment->user() ?? throw new LogicException('Panel access needs a signed-in user: call actingAs() first. Guests are redirected to sign in.');
        $guard = $this->descriptor()->guard ?? $this->container->make(PanelsConfig::class)->guard ?? $this->defaultGuard();
        $context = new PanelContext($this->descriptor(), $user, $guard, $this->container->make(TenantContext::class)->tenant(), $this->descriptor()->routeNamespace($this->container->make(PanelsConfig::class)->namePrefix));

        return $this->container->make(PanelAccess::class)->decide($context);
    }

    private function defaultGuard(): string
    {
        $guard = $this->container->make('config')->get('auth.defaults.guard');

        return is_string($guard) ? $guard : 'web';
    }

    private function manager(): PanelManager
    {
        return $this->container->make(PanelManager::class);
    }
}
