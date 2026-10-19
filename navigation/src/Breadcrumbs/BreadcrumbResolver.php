<?php

declare(strict_types=1);

namespace Gcego\Navigation\Breadcrumbs;

use Gcego\Navigation\Components\Item;
use Gcego\Navigation\Contracts\BreadcrumbProvider;
use Gcego\Navigation\Resolution\ResolvedNavigation;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Breadcrumbs for the current page, from the resolved (already authorized)
 * navigation:
 *
 *   home item → the active item's groups (labels) → the active item → crumbs
 *   of the active item's BreadcrumbProvider (records below it).
 *
 * Unauthorized ancestors are never in the resolved navigation, so they
 * never appear. Without an active item there are no breadcrumbs. A failing
 * provider is reported and its crumbs left out.
 */
final readonly class BreadcrumbResolver
{
    public function __construct(private Container $container) {}

    public function resolve(ResolvedNavigation $navigation): BreadcrumbCollection
    {
        $active = $navigation->active;

        if ($active === null) {
            return new BreadcrumbCollection;
        }

        $crumbs = [];
        $home = $navigation->tree->navigation->home();
        $homeNode = $home === null ? null : $navigation->find($home);

        if ($homeNode !== null && $homeNode->id !== $active->id && $homeNode->label !== null) {
            $crumbs[] = new Breadcrumb($homeNode->label, $homeNode->url);
        }

        foreach ($navigation->trail as $node) {
            if ($node->label !== null) {
                $crumbs[] = new Breadcrumb($node->label, $node->isLink() ? $node->url : null);
            }
        }

        $provider = $active->component instanceof Item ? $active->component->breadcrumbProvider() : null;

        if ($provider !== null) {
            try {
                $instance = $this->container->make($provider);

                foreach ($instance instanceof BreadcrumbProvider ? $instance->breadcrumbs($navigation->context) : [] as $crumb) {
                    if ($crumb instanceof Breadcrumb && trim($crumb->label) !== '') {
                        $crumbs[] = $crumb;
                    }
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return new BreadcrumbCollection(...$crumbs);
    }
}
