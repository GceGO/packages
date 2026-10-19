<?php

declare(strict_types=1);

namespace Gcego\Pages\Routing;

use Closure;
use Gcego\Pages\Exceptions\PageRouteConflictException;
use Gcego\Pages\Http\PageController;
use Gcego\Pages\PagesConfig;
use Gcego\Pages\Records\RecordKey;
use Gcego\Pages\Registry\PageDefinitions;
use Gcego\Registry\Index\DefinitionDescriptor;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * Registers one Laravel route per registered page, through the router:
 *
 *     app(PageRouteRegistrar::class)->register(app('router'));                                  // gcego-pages.routing settings
 *     app(PageRouteRegistrar::class)->register(app('router'), prefix: 'school', namePrefix: 'school.', middleware: ['web', 'auth', 'gcego.tenant:required']);
 *
 * Routes come from the registry index (scalar descriptor metadata): no page
 * definition is loaded. Every route is GET, named, points to PageController
 * (route caching works) and carries only the page identity; the record
 * parameter is constrained by the page's RecordKey. A route name or URL that
 * is already taken, by an application route or another page, throws.
 */
final readonly class PageRouteRegistrar
{
    /** The route action key holding the page identity. */
    public const string ActionKey = 'gcego_page';

    /** The name prefix the route was registered under (its route namespace, e.g. a panel's "gcego.school."). */
    public const string PrefixKey = 'gcego_route_prefix';

    public function __construct(private PageDefinitions $pages, private PagesConfig $config) {}

    /**
     * @param  list<string>|null  $middleware  Replaces gcego-pages.routing.middleware.
     * @param  (Closure(PageRouteDefinition, DefinitionDescriptor): bool)|null  $filter  Which pages to register.
     * @return list<Route>
     *
     * @throws PageRouteConflictException
     */
    public function register(Router $router, ?string $prefix = null, ?string $namePrefix = null, ?array $middleware = null, ?string $domain = null, ?Closure $filter = null): array
    {
        $prefix = trim($prefix ?? $this->config->prefix, '/');
        $namePrefix ??= $this->config->namePrefix;
        $middleware ??= $this->config->middleware;
        $domain ??= $this->config->domain;
        [$names, $uris] = $this->existing($router);
        $routes = [];

        foreach ($this->pages->descriptors() as $descriptor) {
            $definition = PageRouteDefinition::fromDescriptor($descriptor);

            if ($filter !== null && ! $filter($definition, $descriptor)) {
                continue;
            }

            $uri = trim($prefix.'/'.$definition->path, '/');
            $uri = $uri === '' ? '/' : $uri;
            $name = $namePrefix.$definition->route;
            $key = self::uriKey($domain, $uri);

            if (isset($names[$name])) {
                throw PageRouteConflictException::name($name, $descriptor->key(), $names[$name]);
            }

            if (isset($uris[$key])) {
                throw PageRouteConflictException::uri($uri, $descriptor->key(), $uris[$key]);
            }

            $route = $router->get($uri, ['uses' => PageController::class, self::ActionKey => $descriptor->key(), self::PrefixKey => $namePrefix])
                ->name($name)
                ->middleware([...$middleware, ...$definition->middleware]);

            if ($domain !== null) {
                $route->domain($domain);
            }

            $route->where($definition->where);

            if ($definition->record) {
                $route->where('record', RecordKey::pattern($definition->recordKey ?? $this->config->recordKey));
            }

            $names[$name] = 'page '.$descriptor->key();
            $uris[$key] = 'page '.$descriptor->key();
            $routes[] = $route;
        }

        if ($routes !== []) {
            $router->getRoutes()->refreshNameLookups();
            $router->getRoutes()->refreshActionLookups();
        }

        return $routes;
    }

    /**
     * Route names and GET URLs already registered.
     *
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    private function existing(Router $router): array
    {
        $names = [];
        $uris = [];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            $label = $route->getName() === null ? 'route "'.$route->uri().'"' : 'route "'.$route->getName().'"';

            if ($route->getName() !== null) {
                $names[$route->getName()] = $label;
            }

            if (in_array('GET', $route->methods(), true)) {
                $uris[self::uriKey($route->getDomain(), $route->uri())] = $label;
            }
        }

        return [$names, $uris];
    }

    /**
     * URLs that answer the same requests compare equal: parameter names do not matter.
     */
    private static function uriKey(?string $domain, string $uri): string
    {
        return ($domain ?? '*').'|'.preg_replace('/\{[^}]+\}/', '{}', trim($uri, '/'));
    }
}
