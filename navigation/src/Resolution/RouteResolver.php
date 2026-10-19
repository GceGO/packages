<?php

declare(strict_types=1);

namespace Gcego\Navigation\Resolution;

use Gcego\Navigation\Components\Item;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Routing\Router;
use Throwable;

/**
 * Turns items into URLs. Named routes go through Laravel's URL generator
 * (encoding included), with ":tenant" parameters filled from the current
 * tenant; paths and URLs were validated by the definition. Routes are never
 * created here. An item whose route is missing or cannot be generated gets
 * no URL (and is not shown). Generated URLs are memoized for the request.
 */
final class RouteResolver
{
    public const string Tenant = ':tenant';

    /** @var array<string, string|null> */
    private array $urls = [];

    public function __construct(private readonly Router $router, private readonly UrlGenerator $generator, private readonly bool $absolute) {}

    public function url(Item $item, NavigationContext $context): ?string
    {
        $route = $item->routeName();

        if ($route === null) {
            return $item->url();
        }

        $parameters = [];

        foreach ($item->parameters() as $name => $value) {
            if ($value === self::Tenant) {
                if ($context->tenant === null) {
                    return null;
                }

                $value = $context->tenant->key;
            }

            $parameters[$name] = $value;
        }

        $key = $route.'|'.json_encode($parameters);

        if (array_key_exists($key, $this->urls)) {
            return $this->urls[$key];
        }

        if (! $this->exists($route)) {
            return $this->urls[$key] = null;
        }

        try {
            return $this->urls[$key] = $this->generator->route($route, $parameters, $this->absolute);
        } catch (Throwable) {
            return $this->urls[$key] = null;
        }
    }

    public function exists(string $route): bool
    {
        return $this->router->getRoutes()->hasNamedRoute($route);
    }
}
