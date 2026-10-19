<?php

declare(strict_types=1);

namespace Gcego\Panels\Routing;

use Gcego\Pages\Routing\PageRouteRegistrar;
use Gcego\Pages\Routing\PageRouteResolver;
use Gcego\Panels\PanelsConfig;
use Gcego\Panels\Registry\PanelDescriptor;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Throwable;

/**
 * Finds the routes of each panel (from the routes' actions, so cached
 * routes work too) and builds their URLs. The map is rebuilt when the
 * number of routes changes.
 */
final class PanelRoutes
{
    /**
     * @var array<string, array<string, string>> Route namespace => page identity => route name.
     */
    private array $pages = [];

    private int $count = -1;

    public function __construct(private readonly Router $router, private readonly UrlGenerator $urls, private readonly PanelsConfig $config) {}

    /**
     * The route of a page in a panel, or null when the panel does not include it.
     */
    public function pageRoute(PanelDescriptor $panel, string $identity): ?string
    {
        return $this->map()[$panel->routeNamespace($this->config->namePrefix)][$identity] ?? null;
    }

    /**
     * @return array<string, string> Page identity => route name.
     */
    public function pageRoutes(PanelDescriptor $panel): array
    {
        return $this->map()[$panel->routeNamespace($this->config->namePrefix)] ?? [];
    }

    public function route(string $name): ?Route
    {
        return $this->router->getRoutes()->getByName($name);
    }

    /**
     * The panel home route name: its home route, else the route at the panel root.
     */
    public function homeRoute(PanelDescriptor $panel): ?string
    {
        $home = $panel->homeRoute($this->config->namePrefix);

        if ($this->route($home) !== null) {
            return $home;
        }

        foreach ($this->pageRoutes($panel) as $name) {
            $route = $this->route($name);

            $rest = trim(substr(trim($route?->uri() ?? '', '/'), strlen($panel->path)), '/');

            if ($route !== null && ($rest === '' || preg_match('/^\{[a-z_][a-z0-9_]*\}$/', $rest) === 1)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * A root-relative URL of a named route, or null (missing route or parameters).
     *
     * @param  array<string, string|int>  $parameters
     */
    public function url(?string $name, array $parameters = []): ?string
    {
        if ($name === null || $this->route($name) === null) {
            return null;
        }

        try {
            return $this->urls->route($name, $parameters, false);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function map(): array
    {
        $all = $this->router->getRoutes()->getRoutes();

        if (count($all) === $this->count) {
            return $this->pages;
        }

        $pages = [];

        foreach ($all as $route) {
            $identity = PageRouteResolver::pageOf($route);
            $namespace = $route->getAction(PageRouteRegistrar::PrefixKey);

            if ($identity !== null && is_string($namespace) && $route->getName() !== null) {
                $pages[$namespace][$identity] ??= $route->getName();
            }
        }

        $this->count = count($all);

        return $this->pages = $pages;
    }
}
