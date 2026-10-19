<?php

declare(strict_types=1);

namespace Gcego\Pages\Navigation;

use Gcego\Components\Property;
use Gcego\Navigation\Components\Group;
use Gcego\Navigation\Components\Item;
use Gcego\Navigation\Contracts\NavigationContributor;
use Gcego\Navigation\NavigationNode;
use Gcego\Pages\Page;
use Gcego\Pages\PagesConfig;
use Gcego\Pages\Registry\PageDefinitions;
use Gcego\Pages\Routing\PageRouteResolver;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Requirements\Authenticated;
use Gcego\Security\Requirements\PublicAccess;
use Gcego\Security\SecurityConfig;
use Illuminate\Support\Str;

/**
 * Adds the pages that opt in (Page::Navigation true) to a gcego/navigation
 * navigation (gcego-pages.navigation.target). Register it in
 * gcego-navigation.contributors, or set gcego-pages.navigation.auto_register.
 *
 * Each item links to the page's route, carries the page's own requirements
 * (so it is visible exactly when the page may be opened) and is active on
 * the page and the pages below it ("gcego.subjects.*": create, edit, view).
 * Items with a NavigationGroup are grouped by that label and merged into the
 * navigation's group of the same name. Record pages, pages with route
 * parameters other than the tenant, and pages nobody may open (no
 * requirement under the "deny" default) are never added.
 */
final readonly class PageNavigationContributor implements NavigationContributor
{
    public function __construct(
        private PageDefinitions $pages,
        private PageRouteResolver $routes,
        private PagesConfig $config,
        private SecurityConfig $security,
    ) {}

    public function navigation(): string
    {
        return $this->config->navigationTarget;
    }

    public function nodes(): iterable
    {
        /** @var array<string, array{label: string, order: int, items: list<NavigationNode>}> $groups */
        $groups = [];
        $nodes = [];

        foreach ($this->pages->descriptors() as $descriptor) {
            if (($descriptor->metadata['navigation'] ?? false) !== true) {
                continue;
            }

            $page = $this->pages->resolve($descriptor->key());
            $item = $this->item($descriptor->key(), $page);

            if ($item === null) {
                continue;
            }

            $navigation = $page->navigation();

            if ($navigation['group'] === null) {
                $nodes[] = $item;

                continue;
            }

            $key = Str::slug($navigation['group']);
            $groups[$key] ??= ['label' => $navigation['group'], 'order' => $navigation['order'], 'items' => []];
            $groups[$key]['order'] = min($groups[$key]['order'], $navigation['order']);
            $groups[$key]['items'][] = $item;
        }

        foreach ($groups as $group) {
            $nodes[] = new Group($group['label'], new Property(Group::Order, $group['order']), ...$group['items']);
        }

        return $nodes;
    }

    private function item(string $identity, Page $page): ?Item
    {
        $route = $this->routes->routeName($identity);
        $parameters = array_values(array_diff($page->parameters(), [$this->security->tenantParameter]));

        if ($route === null || $page->requiresRecord() || $parameters !== []) {
            return null;
        }

        $requirements = array_values(array_filter($page->configurations()->all(), static fn (mixed $node): bool => $node instanceof Requirement));

        if ($requirements === []) {
            $requirements = match ($this->config->defaultAccess) {
                'authenticated' => [new Authenticated],
                'public' => [new PublicAccess],
                default => null,
            };
        }

        if ($requirements === null) {
            return null;
        }

        $navigation = $page->navigation();
        $properties = [
            new Property(Item::Name, mb_substr('page-'.$page->registryName(), 0, 64)),
            new Property(Item::Label, $navigation['label']),
            new Property(Item::Route, $route),
            new Property(Item::Order, $navigation['order']),
            new Property(Item::Active, [$route.'.*']),
        ];

        if (in_array($this->security->tenantParameter, $page->parameters(), true)) {
            $properties[] = new Property(Item::Parameters, [$this->security->tenantParameter => ':tenant']);
        }

        if ($navigation['icon'] !== null) {
            $properties[] = new Property(Item::Icon, $navigation['icon']);
        }

        return new Item(...$properties, ...$requirements);
    }
}
