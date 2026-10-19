<?php

declare(strict_types=1);

namespace Gcego\Modules\Support;

use Gcego\Pages\Routing\PageRouteRegistrar;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Livewire\Livewire;
use Throwable;

/**
 * The gcego/pages route of the current request: the request's own route,
 * or, for a Livewire update, the route of the page the component lives on
 * (its URL is in the component snapshot, which Livewire checksums).
 */
final readonly class CurrentPageRoute
{
    public function __construct(private Container $container) {}

    public function route(): ?Route
    {
        $request = $this->container->make('request');

        if (! $request instanceof Request) {
            return null;
        }

        $route = $request->route();

        if ($route instanceof Route && $route->getAction(PageRouteRegistrar::ActionKey) !== null) {
            return $route;
        }

        if (! Livewire::isLivewireRequest()) {
            return null;
        }

        try {
            $path = Livewire::originalPath();
            $matched = $this->container->make(Router::class)->getRoutes()->match(Request::create('/'.ltrim(is_string($path) ? $path : '', '/'), 'GET'));
        } catch (Throwable) {
            return null;
        }

        return $matched->getAction(PageRouteRegistrar::ActionKey) !== null ? $matched : null;
    }

    /**
     * The page identity of the current route ("page:academics-subjects").
     */
    public function page(): ?string
    {
        $page = $this->route()?->getAction(PageRouteRegistrar::ActionKey);

        return is_string($page) ? $page : null;
    }
}
