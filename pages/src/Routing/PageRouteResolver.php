<?php

declare(strict_types=1);

namespace Gcego\Pages\Routing;

use Gcego\Pages\Page;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Livewire\Livewire;
use Throwable;

/**
 * Finds the routes of registered pages (from the routes' "gcego_page" action,
 * so cached routes and routes registered under another prefix are found too)
 * and builds their URLs.
 *
 * The map is rebuilt whenever the number of routes changes, so routes added
 * after first use (a panel registering its pages) are picked up.
 */
final class PageRouteResolver
{
    /**
     * @var array<string, list<string>> Page identity => route names, in registration order.
     */
    private array $routes = [];

    private int $count = -1;

    /**
     * @var array<string, string|null> Route name => its name prefix.
     */
    private array $prefixes = [];

    public function __construct(private readonly Router $router, private readonly UrlGenerator $urls) {}

    /**
     * The name of a page's route, or null when it has none. A page routed in
     * several namespaces (the same page in two panels) uses the route in the
     * current request's namespace, so links and redirects stay in it.
     */
    public function routeName(string $identity): ?string
    {
        $names = $this->map()[$identity] ?? [];

        if (count($names) > 1) {
            $prefix = $this->currentPrefix();

            foreach ($prefix === null ? [] : $names as $name) {
                if (($this->prefixes[$name] ?? null) === $prefix) {
                    return $name;
                }
            }
        }

        return $names[0] ?? null;
    }

    /**
     * The route namespace (name prefix) of the current request's page route,
     * also for Livewire updates of components on that page.
     */
    public function currentPrefix(): ?string
    {
        $request = request();
        $route = $request->route();

        if ($route instanceof Route && self::pageOf($route) !== null) {
            $prefix = $route->getAction(PageRouteRegistrar::PrefixKey);

            return is_string($prefix) ? $prefix : null;
        }

        if (! class_exists(Livewire::class) || ! Livewire::isLivewireRequest()) {
            return null;
        }

        try {
            $path = Livewire::originalPath();
            $matched = $this->router->getRoutes()->match(Request::create('/'.ltrim(is_string($path) ? $path : '', '/'), 'GET'));
        } catch (Throwable) {
            return null;
        }

        $prefix = self::pageOf($matched) === null ? null : $matched->getAction(PageRouteRegistrar::PrefixKey);

        return is_string($prefix) ? $prefix : null;
    }

    /**
     * @return list<string>
     */
    public function routeNames(string $identity): array
    {
        return $this->map()[$identity] ?? [];
    }

    /**
     * The page identity a route serves, or null for other routes.
     */
    public static function pageOf(?Route $route): ?string
    {
        $identity = $route?->getAction(PageRouteRegistrar::ActionKey);

        return is_string($identity) && $identity !== '' ? $identity : null;
    }

    /**
     * A root-relative URL of the page, or null when it has no route or the
     * parameters do not fit it.
     *
     * @param  array<string, string|int>  $parameters
     */
    public function url(string $identity, string|int|null $record = null, array $parameters = []): ?string
    {
        $name = $this->routeName($identity);

        if ($name === null) {
            return null;
        }

        if ($record !== null) {
            $parameters[Page::RecordParameter] = $record;
        }

        try {
            return $this->urls->route($name, $parameters, false);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function map(): array
    {
        $all = $this->router->getRoutes()->getRoutes();

        if (count($all) === $this->count) {
            return $this->routes;
        }

        $routes = [];

        foreach ($all as $route) {
            $identity = self::pageOf($route);

            if ($identity !== null && $route->getName() !== null) {
                $routes[$identity][] = $route->getName();
                $prefix = $route->getAction(PageRouteRegistrar::PrefixKey);
                $this->prefixes[$route->getName()] = is_string($prefix) ? $prefix : null;
            }
        }

        $this->count = count($all);

        return $this->routes = $routes;
    }
}
