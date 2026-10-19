<?php

declare(strict_types=1);

namespace Gcego\Cli\Inspection;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * gcego:routes: the routes GCEGO registered (named gcego.*), read from the
 * router as it is — nothing is registered here. Paths show their parameter
 * placeholders, never values.
 */
final readonly class RouteInspector
{
    public function __construct(private Router $router) {}

    /**
     * @return list<array{name: string, methods: list<string>, uri: string, domain: string|null, panel: string|null, middleware: list<string>}>
     */
    public function routes(?string $panel = null, ?string $module = null): array
    {
        $rows = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            assert($route instanceof Route);
            $name = (string) $route->getName();

            if (! str_starts_with($name, 'gcego.')) {
                continue;
            }

            $segments = explode('.', $name);
            $owner = count($segments) > 2 ? $segments[1] : null;

            if (($panel !== null && $owner !== $panel) || ($module !== null && ! in_array($module, array_slice($segments, 2), true))) {
                continue;
            }

            $rows[] = [
                'name' => $name,
                'methods' => array_values(array_diff($route->methods(), ['HEAD'])),
                'uri' => '/'.ltrim($route->uri(), '/'),
                'domain' => $route->getDomain(),
                'panel' => $owner,
                'middleware' => array_values(array_map(static fn (mixed $middleware): string => is_string($middleware) ? $middleware : get_debug_type($middleware), $route->gatherMiddleware())),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $a['uri'] <=> $b['uri']);

        return $rows;
    }
}
