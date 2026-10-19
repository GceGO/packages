<?php

declare(strict_types=1);

namespace Gcego\Navigation\Diagnostics;

use Gcego\Chroma\Slots\SlotRegistry;
use Gcego\Chroma\Tailwind;
use Gcego\Navigation\Components\Item;
use Gcego\Navigation\Navigation;
use Gcego\Navigation\NavigationConfig;
use Gcego\Navigation\NavigationManager;
use Gcego\Navigation\Resolution\ActiveStateResolver;
use Gcego\Navigation\Resolution\RouteResolver;
use Gcego\Navigation\Tree\Node;
use Gcego\Tables\Support\Icons;
use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\Router;
use Stringable;
use Throwable;

/**
 * Inspects a navigation without resolving it for anyone: no authorization
 * decisions, badge providers, breadcrumb providers or queries run, and no
 * badge values are reported.
 *
 * Checks: schema, registry, structure (duplicates, depth, cycles,
 * contributions), routes (missing routes, patterns matching no route),
 * icons, badges, breadcrumbs, authorization (nodes visible by the default
 * rule), styling (unknown slots), accessibility and performance.
 */
final readonly class NavigationInspector
{
    public function __construct(private Container $container, private NavigationConfig $config) {}

    public function inspect(Navigation|string $navigation): NavigationReport
    {
        $manager = $this->container->make(NavigationManager::class);
        $identity = null;

        if (is_string($navigation)) {
            try {
                $identity = $manager->identity($navigation);
                $navigation = $manager->resolve($identity);
            } catch (Throwable) {
                return new NavigationReport($identity, [], [new NavigationDiagnostic('registry', NavigationDiagnostic::Error, sprintf('"%s" is not a registered navigation.', $navigation))]);
            }
        }

        $diagnostics = array_map(static fn (object $error): NavigationDiagnostic => new NavigationDiagnostic('schema', NavigationDiagnostic::Error, $error instanceof Stringable ? (string) $error : 'invalid'), $navigation->validate()->errors());

        try {
            $tree = $manager->tree($navigation, $identity);
        } catch (Throwable $exception) {
            return new NavigationReport($identity, [], [...$diagnostics, new NavigationDiagnostic('structure', NavigationDiagnostic::Error, $exception->getMessage())]);
        }

        $nodes = [];
        $badges = 0;
        $labels = [];

        foreach ($tree->all() as $node) {
            $nodes[] = ['id' => $node->id, 'path' => $node->path, 'type' => $node->type, 'depth' => $node->depth, 'requirements' => array_map(static fn (object $requirement): string => $requirement::class, $node->requirements)];
            array_push($diagnostics, ...$this->routes($node), ...$this->icons($node), ...$this->styling($node));

            if ($node->component instanceof Item && $node->component->badgeProvider() !== null) {
                $badges++;
            }

            if ($node->component->label() !== null && ($node->type === 'item' || $node->type === 'external')) {
                $labels[$node->component->label()][] = $node->id;
            }
        }

        foreach ($labels as $label => $ids) {
            if (count($ids) > 1) {
                $diagnostics[] = new NavigationDiagnostic('accessibility', NavigationDiagnostic::Warning, sprintf('%d links are labelled "%s" (%s): screen-reader users cannot tell them apart.', count($ids), $label, implode(', ', $ids)));
            }
        }

        array_push($diagnostics, ...$this->authorization($tree->nodes, $tree->requirements !== []));

        if (count($nodes) > $this->config->maxNodes) {
            $diagnostics[] = new NavigationDiagnostic('performance', NavigationDiagnostic::Warning, sprintf('%d nodes (more than %d): split it into several navigations.', count($nodes), $this->config->maxNodes));
        }

        if ($badges > $this->config->maxBadges) {
            $diagnostics[] = new NavigationDiagnostic('performance', NavigationDiagnostic::Warning, sprintf('%d badge providers (more than %d) run on every render: cache them (Item::BadgeTtl) or drop some.', $badges, $this->config->maxBadges));
        }

        return new NavigationReport($tree->identity, $nodes, $diagnostics);
    }

    /**
     * @return list<NavigationDiagnostic>
     */
    private function routes(Node $node): array
    {
        if (! $node->component instanceof Item) {
            return [];
        }

        $diagnostics = [];
        $routes = $this->container->make(RouteResolver::class);
        $route = $node->component->routeName();

        if ($route !== null && ! $routes->exists($route)) {
            $diagnostics[] = new NavigationDiagnostic('routes', NavigationDiagnostic::Error, sprintf('Route "%s" does not exist; the item is never shown.', $route), $node->id);
        }

        $names = array_filter(array_map(static fn ($candidate): ?string => $candidate->getName(), $this->container->make(Router::class)->getRoutes()->getRoutes()));

        foreach ($node->component->activePatterns() as $pattern) {
            if (! str_starts_with($pattern, '/') && array_filter($names, static fn (string $name): bool => ActiveStateResolver::routeMatches($pattern, $name)) === []) {
                $diagnostics[] = new NavigationDiagnostic('routes', NavigationDiagnostic::Warning, sprintf('Active pattern "%s" matches no route.', $pattern), $node->id);
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<NavigationDiagnostic>
     */
    private function icons(Node $node): array
    {
        $icon = $node->component->icon();

        return $icon === null || $this->container->make(Icons::class)->has($icon)
            ? []
            : [new NavigationDiagnostic('icons', NavigationDiagnostic::Warning, sprintf('Icon "%s" is not registered (Icons::register()); it is not shown.', $icon), $node->id)];
    }

    /**
     * @return list<NavigationDiagnostic>
     */
    private function styling(Node $node): array
    {
        $slots = array_keys($this->container->make(SlotRegistry::class)->for($node->component) ?? []);
        $diagnostics = [];

        foreach ($node->component->configurations()->ofClass(Tailwind::class) as $tailwind) {
            foreach ($tailwind->slotNames() as $slot) {
                if (! in_array($slot, $slots, true)) {
                    $diagnostics[] = new NavigationDiagnostic('styling', NavigationDiagnostic::Error, sprintf('Slot "%s" is not a slot of %s (slots: %s).', $slot, class_basename($node->component), implode(', ', $slots)), $node->id);
                }
            }
        }

        return $diagnostics;
    }

    /**
     * One finding for the links whose whole chain has no requirement, so the default rule decides.
     *
     * @param  list<Node>  $nodes
     * @return list<NavigationDiagnostic>
     */
    private function authorization(array $nodes, bool $inherited): array
    {
        $unguarded = $this->unguarded($nodes, $inherited);

        if ($unguarded === []) {
            return [];
        }

        return [new NavigationDiagnostic('authorization', NavigationDiagnostic::Warning, sprintf('%d links have no requirement (%s): %s', count($unguarded), implode(', ', $unguarded), match ($this->config->defaultVisibility) {
            'deny' => 'they are never shown (visibility.default is "deny").',
            'public' => 'they are shown to everyone, guests included (visibility.default is "public").',
            default => 'they are shown to every signed-in user (visibility.default is "authenticated").',
        }))];
    }

    /**
     * @param  list<Node>  $nodes
     * @return list<string>
     */
    private function unguarded(array $nodes, bool $inherited): array
    {
        $ids = [];

        foreach ($nodes as $node) {
            $guarded = $inherited || $node->requirements !== [];

            if (! $guarded && ($node->type === 'item' || $node->type === 'external')) {
                $ids[] = $node->id;
            }

            array_push($ids, ...$this->unguarded($node->children, $guarded));
        }

        return $ids;
    }
}
