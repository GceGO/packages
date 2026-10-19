<?php

declare(strict_types=1);

namespace Gcego\Modules\Navigation;

use Gcego\Components\Property;
use Gcego\Modules\Definitions\ModuleDefinition;
use Gcego\Modules\ModuleManager;
use Gcego\Modules\ModulesConfig;
use Gcego\Modules\Operation;
use Gcego\Navigation\Components\Group;
use Gcego\Navigation\Components\Item;
use Gcego\Navigation\Contracts\NavigationContributor;
use Gcego\Pages\Page;
use Gcego\Pages\PagesConfig;
use Gcego\Pages\Registry\PageDefinitions;
use Gcego\Pages\Routing\PageRouteResolver;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Requirements\Authenticated;
use Gcego\Security\Requirements\PublicAccess;
use Gcego\Security\SecurityConfig;
use Throwable;

/**
 * Adds modules to a gcego/navigation navigation (gcego-modules.navigation.target):
 * each module that opts in (Module::Navigation, or every module with
 * gcego-modules.navigation.auto) becomes a group of its resources' list
 * pages and its own pages without parameters.
 *
 * Each item carries the page's own requirements, so it is visible exactly
 * when the page may be opened (gcego/navigation evaluates them per user and
 * tenant), and is active on every page of the resource, its relationship
 * pages included. Pages nobody may open are never added.
 */
final readonly class ModuleNavigationContributor implements NavigationContributor
{
    public function __construct(
        private ModuleManager $modules,
        private ModulesConfig $config,
        private PageDefinitions $pages,
        private PageRouteResolver $routes,
        private PagesConfig $pagesConfig,
        private SecurityConfig $security,
    ) {}

    public function navigation(): string
    {
        return $this->config->navigationTarget;
    }

    public function nodes(): iterable
    {
        $groups = [];

        foreach ($this->modules->names() as $name) {
            try {
                $module = $this->modules->module($name);
            } catch (Throwable) {
                continue;
            }

            if (($module->navigation ?? $this->config->autoNavigation) !== true) {
                continue;
            }

            $items = $this->items($module);

            if ($items !== []) {
                $groups[] = new Group($module->navigationGroup, new Property(Group::Name, 'module-'.$module->name), new Property(Group::Order, $module->navigationOrder), ...$items);
            }
        }

        return $groups;
    }

    /**
     * @return list<Item>
     */
    private function items(ModuleDefinition $module): array
    {
        $items = [];

        foreach ($module->resources as $position => $resource) {
            if (! $resource->navigation || ! $resource->exposes(Operation::Index)) {
                continue;
            }

            $item = $this->item($module, $resource->pageName(Operation::Index), $resource->navigationLabel, $resource->navigationOrder ?? $position, $resource->icon, 'resource-'.$resource->name, $resource->pageBase, $resource->navigationBadge ? $resource->model : null, $resource->navigationBadgeVariant);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        foreach ($module->pages as $position => $page) {
            $item = $this->item($module, $page->pageName(), $page->navigation()['label'], $page->navigation()['order'] ?: 100 + $position, $page->navigation()['icon'], 'page-'.$page->registryName(), null);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param  class-string|null  $badgeCount  a model whose record count is the badge (read as "12 <label>")
     * @param  string|null  $badgeVariant  its color
     */
    private function item(ModuleDefinition $module, string $pageName, string $label, int $order, ?string $icon, string $name, ?string $activeBase, ?string $badgeCount = null, ?string $badgeVariant = null): ?Item
    {
        $identity = $this->modules->pageIdentity($module->name, $pageName);

        if (! $this->pages->has($identity)) {
            return null;
        }

        $page = $this->pages->resolve($identity);
        $route = $this->routes->routeName($identity);
        $parameters = array_values(array_diff($page->parameters(), [$this->security->tenantParameter]));

        if ($route === null || $page->requiresRecord() || $parameters !== []) {
            return null;
        }

        $requirements = $this->requirements($page);

        if ($requirements === null) {
            return null;
        }

        $active = [$route];

        if ($activeBase !== null) {
            $active[] = $this->pagesConfig->namePrefix.$activeBase.'.*';
        }

        $properties = [
            new Property(Item::Name, mb_substr($name, 0, 64)),
            new Property(Item::Label, $label),
            new Property(Item::Route, $route),
            new Property(Item::Order, $order),
            new Property(Item::Active, $active),
        ];

        if (in_array($this->security->tenantParameter, $page->parameters(), true)) {
            $properties[] = new Property(Item::Parameters, [$this->security->tenantParameter => ':tenant']);
        }

        if ($icon !== null) {
            $properties[] = new Property(Item::Icon, $icon);
        }

        if ($badgeCount !== null) {
            $properties[] = new Property(Item::BadgeCount, $badgeCount);
            $properties[] = new Property(Item::BadgeLabel, mb_substr(mb_strtolower($label), 0, 80));

            if ($badgeVariant !== null) {
                $properties[] = new Property(Item::BadgeVariant, $badgeVariant);
            }
        }

        return new Item(...$properties, ...$requirements);
    }

    /**
     * The page's own requirements, or the pages default access; null when nobody may open it.
     *
     * @return list<Requirement>|null
     */
    private function requirements(Page $page): ?array
    {
        $requirements = array_values(array_filter($page->configurations()->all(), static fn (mixed $node): bool => $node instanceof Requirement));

        if ($requirements !== []) {
            return $requirements;
        }

        return match ($this->pagesConfig->defaultAccess) {
            'authenticated' => [new Authenticated],
            'public' => [new PublicAccess],
            default => null,
        };
    }
}
