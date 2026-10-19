<?php

declare(strict_types=1);

namespace Gcego\Panels\Routing;

use Gcego\Pages\Routing\PageRouteRegistrar;
use Gcego\Panels\Exceptions\InvalidPanelException;
use Gcego\Panels\Extensions\PanelExtensions;
use Gcego\Panels\Http\Controllers\PanelHomeController;
use Gcego\Panels\Http\Middleware\AuthorizePanel;
use Gcego\Panels\PanelsConfig;
use Gcego\Panels\Registry\PanelDefinitions;
use Gcego\Panels\Registry\PanelDescriptor;
use Gcego\Panels\Registry\PanelMembership;
use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Security\Middleware\ResolveTenant;
use Gcego\Security\SecurityConfig;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * Registers every panel's routes from the registry index: the routes of
 * its pages (through gcego/pages' PageRouteRegistrar, so page routing is
 * not reimplemented) under the panel's prefix, route name namespace and
 * domain, plus the panel home ("<namespace>home").
 *
 * Middleware, in order: gcego-panels.routing.middleware, Laravel "auth"
 * (with the panel's guard), "verified" (Panel::Verified), "gcego.tenant"
 * (Panel::Tenant), "gcego.panel:<name>", then the panel's own middleware
 * and each page's. A page is routed only in the panels that include it.
 * PanelExtensions add their routes inside the same group.
 */
final readonly class PanelRouteRegistrar
{
    public const string ActionKey = 'gcego_panel';

    public function __construct(
        private PanelDefinitions $panels,
        private PanelMembership $membership,
        private PageRouteRegistrar $pages,
        private PanelsConfig $config,
        private SecurityConfig $security,
        private PanelExtensions $extensions,
    ) {}

    /**
     * @return list<Route>
     *
     * @throws InvalidPanelException
     */
    public function register(Router $router): array
    {
        $routes = [];
        $locations = [];
        $namespaces = [];

        foreach ($this->panels->descriptors() as $panel) {
            $location = ($panel->domain ?? '*').'|'.$panel->path;
            $namespace = $panel->routeNamespace($this->config->namePrefix);

            if (isset($locations[$location])) {
                throw InvalidPanelException::invalid('Panel "'.$panel->name.'"', sprintf('the panel "%s" already uses the path "/%s"%s.', $locations[$location], $panel->path, $panel->domain === null ? '' : ' on '.$panel->domain));
            }

            if (isset($namespaces[$namespace])) {
                throw InvalidPanelException::invalid('Panel "'.$panel->name.'"', sprintf('the panel "%s" already uses the route names "%s*".', $namespaces[$namespace], $namespace));
            }

            $locations[$location] = $panel->name;
            $namespaces[$namespace] = $panel->name;
            $routes = [...$routes, ...$this->registerPanel($router, $panel)];
        }

        return $routes;
    }

    /**
     * @return list<Route>
     */
    public function registerPanel(Router $router, PanelDescriptor $panel): array
    {
        $members = $this->membership->pages($panel);
        $prefix = $this->prefix($panel);
        $middleware = $this->middleware($panel);
        $namespace = $panel->routeNamespace($this->config->namePrefix);

        $routes = $this->pages->register(
            $router,
            prefix: $prefix,
            namePrefix: $namespace,
            middleware: $middleware,
            domain: $panel->domain,
            filter: static fn (mixed $definition, DefinitionDescriptor $descriptor): bool => array_key_exists($descriptor->key(), $members),
        );

        $home = $prefix === '' ? '/' : $prefix;
        $taken = false;

        foreach ($routes as $route) {
            $taken = $taken || trim($route->uri(), '/') === trim($home, '/');
        }

        $before = count($router->getRoutes()->getRoutes());
        $group = ['prefix' => $prefix, 'middleware' => $middleware, 'as' => $namespace];

        if ($panel->domain !== null) {
            $group['domain'] = $panel->domain;
        }

        foreach ($this->extensions->all() as $extension) {
            $router->group($group, static fn (Router $router) => $extension->routes($router, $panel));
        }

        if (count($router->getRoutes()->getRoutes()) !== $before) {
            $router->getRoutes()->refreshNameLookups();
            $router->getRoutes()->refreshActionLookups();
        }

        if (! $taken) {
            $route = $router->get($home, ['uses' => PanelHomeController::class, self::ActionKey => $panel->name])
                ->name($panel->homeRoute($this->config->namePrefix))
                ->middleware($middleware);

            if ($panel->domain !== null) {
                $route->domain($panel->domain);
            }

            $routes[] = $route;
            $router->getRoutes()->refreshNameLookups();
            $router->getRoutes()->refreshActionLookups();
        }

        return $routes;
    }

    /**
     * The URL prefix: the panel path, then "{tenant}" for tenant-required
     * panels whose domain does not carry the tenant.
     */
    public function prefix(PanelDescriptor $panel): string
    {
        $parameter = '{'.$this->security->tenantParameter.'}';

        if ($panel->tenant === 'required' && ! str_contains($panel->domain ?? '', $parameter)) {
            return trim($panel->path.'/'.$parameter, '/');
        }

        return $panel->path;
    }

    /**
     * @return list<string>
     */
    public function middleware(PanelDescriptor $panel): array
    {
        $guard = $panel->guard ?? $this->config->guard;
        $middleware = [...$this->config->middleware, $guard === null ? 'auth' : 'auth:'.$guard];

        if ($panel->verified) {
            $middleware[] = 'verified';
        }

        if ($panel->tenant !== null) {
            $middleware[] = ResolveTenant::Alias.':'.$panel->tenant;
        }

        return [...$middleware, AuthorizePanel::Alias.':'.$panel->name, ...$panel->middleware];
    }
}
