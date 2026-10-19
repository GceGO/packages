<?php

declare(strict_types=1);

namespace Gcego\Panels\Diagnostics;

use Gcego\Chroma\ChromaManager;
use Gcego\Panels\Panel;
use Gcego\Panels\PanelsConfig;
use Gcego\Panels\Registry\PanelDefinitions;
use Gcego\Panels\Registry\PanelMembership;
use Gcego\Panels\Routing\PanelRouteRegistrar;
use Gcego\Panels\Routing\PanelRoutes;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Security\SecurityConfig;
use Illuminate\Contracts\Config\Repository;
use Throwable;

/**
 * Explains a panel: its routes, pages, middleware and access, and the
 * problems that would make it unusable or unsafe (no access rules, missing
 * modules or pages, an unknown guard, theme or navigation...). Reads
 * definitions and routes only; it never queries or authorizes anyone.
 */
final readonly class PanelInspector
{
    public function __construct(
        private PanelDefinitions $panels,
        private PanelMembership $membership,
        private PanelRoutes $routes,
        private PanelRouteRegistrar $registrar,
        private RegistryInterface $registry,
        private PanelsConfig $config,
        private Repository $settings,
        private ChromaManager $styling,
        private SecurityConfig $security,
    ) {}

    public function inspect(string $name): PanelReport
    {
        $descriptor = $this->panels->descriptor($name);
        $panel = $this->panels->resolve($name);
        $diagnostics = [];

        if (! $panel->declaresAccess()) {
            $diagnostics[] = PanelDiagnostic::error('access.undeclared', 'The panel declares no requirement and no AccessPolicy, so nobody may enter it (panels are deny-by-default).');
        }

        $members = [];

        foreach ($panel->modules() as $module) {
            try {
                foreach ($this->membership->modulePages($descriptor, $module) as $identity) {
                    $members[] = $identity;
                }
            } catch (Throwable $exception) {
                $diagnostics[] = PanelDiagnostic::error('module.missing', $exception->getMessage());
            }
        }

        foreach ($panel->pages() as $page) {
            try {
                $members[] = $this->membership->pageIdentity($descriptor, $page);
            } catch (Throwable $exception) {
                $diagnostics[] = PanelDiagnostic::error('page.missing', $exception->getMessage());
            }
        }

        $routes = $this->routes->pageRoutes($descriptor);

        if ($members !== [] && $routes === []) {
            $diagnostics[] = PanelDiagnostic::warning('routes.missing', 'The panel has no routes; are routes cached from before it was added (php artisan route:clear), or is gcego-panels.routing.register_page_routes off?');
        }

        $guard = $descriptor->guard ?? $this->config->guard ?? (is_string($default = $this->settings->get('auth.defaults.guard')) ? $default : 'web');

        if (! is_array($this->settings->get('auth.guards.'.$guard))) {
            $diagnostics[] = PanelDiagnostic::error('guard.unknown', sprintf('The guard "%s" is not configured in auth.guards.', $guard));
        }

        if ($panel->get(Panel::Navigation) !== null && ! $this->registry->has('navigation:'.$panel->navigationName())) {
            $diagnostics[] = PanelDiagnostic::error('navigation.missing', sprintf('The navigation "%s" is not registered.', $panel->navigationName()));
        }

        $theme = $panel->theme() ?? $this->config->defaultTheme;

        if ($theme !== null) {
            try {
                $this->styling->theme($theme);
            } catch (Throwable) {
                $diagnostics[] = PanelDiagnostic::warning('theme.unknown', sprintf('The theme "%s" is not registered with gcego/chroma.', $theme));
            }
        }

        if ($descriptor->tenant !== null && ! $this->security->tenancy) {
            $diagnostics[] = PanelDiagnostic::warning('tenancy.disabled', 'The panel uses tenancy, but gcego/security tenancy is disabled.');
        }

        return new PanelReport(
            identity: $descriptor->identity,
            title: $descriptor->title,
            path: '/'.$this->registrar->prefix($descriptor),
            domain: $descriptor->domain,
            routeNamespace: $descriptor->routeNamespace($this->config->namePrefix),
            guard: $guard,
            tenant: $descriptor->tenant,
            navigation: 'navigation:'.$descriptor->navigation,
            dashboard: $descriptor->dashboard,
            modules: $descriptor->modules,
            pages: array_values(array_unique($members)),
            routes: $routes,
            middleware: $this->registrar->middleware($descriptor),
            diagnostics: $diagnostics,
        );
    }
}
