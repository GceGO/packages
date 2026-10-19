<?php

declare(strict_types=1);

namespace Gcego\Panels\Navigation;

use Gcego\Components\Property;
use Gcego\Modules\Definitions\ModuleDefinition;
use Gcego\Modules\ModuleManager;
use Gcego\Modules\Operation;
use Gcego\Navigation\Components\Group;
use Gcego\Navigation\Components\Item;
use Gcego\Navigation\Contracts\MultipleNavigationsContributor;
use Gcego\Navigation\NavigationNode;
use Gcego\Pages\Page;
use Gcego\Pages\PagesConfig;
use Gcego\Pages\Registry\PageDefinitions;
use Gcego\Panels\PanelsConfig;
use Gcego\Panels\Registry\PanelDefinitions;
use Gcego\Panels\Registry\PanelDescriptor;
use Gcego\Panels\Registry\PanelMembership;
use Gcego\Panels\Routing\PanelRoutes;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\Requirements\Authenticated;
use Gcego\Security\Requirements\PublicAccess;
use Gcego\Security\SecurityConfig;
use Illuminate\Support\Str;
use Throwable;

/**
 * Builds each panel's navigation ("navigation:panel-<name>", or the
 * navigation the panel names): the dashboard, the referenced pages
 * (grouped by their NavigationGroup) and a group per module with its
 * resources' list pages and its own pages, all linking to the panel's
 * routes.
 *
 * Items carry the page's own requirements, so gcego/navigation shows each
 * one exactly when the page may be opened; pages nobody may open, pages
 * that need a record or parameters, and pages outside the panel are never
 * added. Structure only: it is cached per process and evaluated per user.
 */
final readonly class PanelNavigationContributor implements MultipleNavigationsContributor
{
    public function __construct(
        private PanelDefinitions $panels,
        private PanelMembership $membership,
        private PanelRoutes $routes,
        private PageDefinitions $pages,
        private ModuleManager $modules,
        private PanelsConfig $config,
        private PagesConfig $pagesConfig,
        private SecurityConfig $security,
    ) {}

    public function navigation(): string
    {
        return 'panel-none';
    }

    public function nodes(): iterable
    {
        return [];
    }

    public function contributesTo(string $identity): bool
    {
        return $this->panelsOf($identity) !== [];
    }

    public function nodesFor(string $identity): iterable
    {
        $nodes = [];

        foreach ($this->panelsOf($identity) as $panel) {
            $nodes = [...$nodes, ...$this->panelNodes($panel)];
        }

        return $nodes;
    }

    /**
     * @return list<NavigationNode>
     */
    public function panelNodes(PanelDescriptor $panel): array
    {
        $nodes = [];
        $groups = [];
        $dashboard = null;

        if ($panel->dashboard !== null) {
            try {
                $dashboard = $this->membership->pageIdentity($panel, $panel->dashboard);
                $item = $this->item($panel, $dashboard, 'dashboard', -1000, null, force: true);

                if ($item !== null) {
                    $nodes[] = $item;
                }
            } catch (Throwable) {
                $dashboard = null;
            }
        }

        foreach ($panel->pages as $position => $name) {
            try {
                $identity = $this->membership->pageIdentity($panel, $name);
            } catch (Throwable) {
                continue;
            }

            if ($identity === $dashboard || in_array($name, $panel->unlisted, true)) {
                continue;
            }

            $page = $this->pages->resolve($identity);

            $item = $this->item($panel, $identity, 'page-'.Str::slug(str_replace('.', '-', $name)), $page->navigation()['order'] ?: $position, null);

            if ($item === null) {
                continue;
            }

            $group = $page->navigation()['group'];

            if ($group === null) {
                $nodes[] = $item;
            } else {
                $groups[$group][] = $item;
            }
        }

        foreach ($groups as $label => $items) {
            $nodes[] = new Group($label, new Property(Group::Name, mb_substr('group-'.Str::slug($label), 0, 64)), ...$items);
        }

        foreach ($panel->modules as $position => $name) {
            try {
                $module = $this->modules->module($name);
            } catch (Throwable) {
                continue;
            }

            if ($module->navigation === false) {
                continue;
            }

            $items = $this->moduleItems($panel, $module);

            if ($items !== []) {
                $nodes[] = new Group($module->navigationGroup, new Property(Group::Name, 'module-'.$module->name), new Property(Group::Order, $module->navigationOrder ?: 100 + $position), ...$items);
            }
        }

        return $nodes;
    }

    /**
     * @return list<PanelDescriptor>
     */
    private function panelsOf(string $identity): array
    {
        return array_values(array_filter($this->panels->descriptors(), static fn (PanelDescriptor $panel): bool => 'navigation:'.$panel->navigation === $identity));
    }

    /**
     * @return list<Item>
     */
    private function moduleItems(PanelDescriptor $panel, ModuleDefinition $module): array
    {
        $items = [];
        $namespace = $panel->routeNamespace($this->config->namePrefix);

        foreach ($module->resources as $position => $resource) {
            if (! $resource->navigation || ! $resource->exposes(Operation::Index)) {
                continue;
            }

            $item = $this->item($panel, $this->modules->pageIdentity($module->name, $resource->pageName(Operation::Index)), 'resource-'.$resource->name, $resource->navigationOrder ?? $position, $namespace.$resource->pageBase.'.*', label: $resource->navigationLabel, icon: $resource->icon, badgeCount: $resource->navigationBadge ? $resource->model : null, badgeVariant: $resource->navigationBadgeVariant);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        foreach ($module->pages as $position => $page) {
            $item = $this->item($panel, $this->modules->pageIdentity($module->name, $page->pageName()), 'page-'.$page->registryName(), $page->navigation()['order'] ?: 100 + $position, null);

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
    private function item(PanelDescriptor $panel, string $identity, string $name, int $order, ?string $active, bool $force = false, ?string $label = null, ?string $icon = null, ?string $badgeCount = null, ?string $badgeVariant = null): ?Item
    {
        $route = $this->routes->pageRoute($panel, $identity);

        if ($route === null || ! $this->pages->has($identity)) {
            return null;
        }

        $page = $this->pages->resolve($identity);
        $parameters = array_values(array_diff($page->parameters(), [$this->security->tenantParameter]));

        if ($page->requiresRecord() || $parameters !== []) {
            return null;
        }

        $requirements = $this->requirements($page);

        if ($requirements === null) {
            return null;
        }

        $properties = [
            new Property(Item::Name, mb_substr($name, 0, 64)),
            new Property(Item::Label, $label ?? $page->navigation()['label']),
            new Property(Item::Route, $route),
            new Property(Item::Order, $order),
            new Property(Item::Active, $active === null ? [$route] : [$route, $active]),
        ];

        if (in_array($this->security->tenantParameter, $this->routes->route($route)?->parameterNames() ?? [], true)) {
            $properties[] = new Property(Item::Parameters, [$this->security->tenantParameter => ':tenant']);
        }

        $icon ??= $page->navigation()['icon'] ?? ($force ? 'navigation.home' : null);

        if ($icon !== null) {
            $properties[] = new Property(Item::Icon, $icon);
        }

        if ($badgeCount !== null) {
            $properties[] = new Property(Item::BadgeCount, $badgeCount);
            $properties[] = new Property(Item::BadgeLabel, mb_substr(mb_strtolower((string) ($label ?? $page->navigation()['label'])), 0, 80));

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
