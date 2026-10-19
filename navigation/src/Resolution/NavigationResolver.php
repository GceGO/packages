<?php

declare(strict_types=1);

namespace Gcego\Navigation\Resolution;

use Gcego\Data\DataManager;
use Gcego\Navigation\Badges\BadgeResolver;
use Gcego\Navigation\Components\ExternalLink;
use Gcego\Navigation\Components\Group;
use Gcego\Navigation\Components\Item;
use Gcego\Navigation\NavigationConfig;
use Gcego\Navigation\Tree\NavigationTree;
use Gcego\Navigation\Tree\Node;
use Gcego\Security\Contracts\Requirement;
use Gcego\Security\SecurityManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Livewire\Livewire;
use Throwable;

/**
 * Resolves a navigation tree for the current request (request-scoped):
 *
 * 1. visibility: each node's requirement chain (gcego/security);
 * 2. URLs: routes and paths (a link without a URL is not shown);
 * 3. the active item: the best ActiveStateResolver score (one at most);
 * 4. groups: hidden when nothing in them is visible, expanded when they
 *    hold the active item; stray headings and dividers are dropped;
 * 5. badges of visible items (lazy).
 *
 * Results are memoized per navigation, request and security context.
 */
final class NavigationResolver
{
    /** @var array<string, ResolvedNavigation> */
    private array $resolved = [];

    /** @var array<string, Request> Pages of Livewire updates, by original URL. */
    private array $pages = [];

    public function __construct(
        private readonly Container $container,
        private readonly SecurityManager $security,
        private readonly DataManager $data,
        private readonly NavigationConfig $config,
        private readonly VisibilityResolver $visibility,
        private readonly RouteResolver $routes,
        private readonly BadgeResolver $badges,
    ) {}

    public function resolve(NavigationTree $tree): ResolvedNavigation
    {
        $context = $this->context($tree->identity);
        $request = $this->request();
        $key = $tree->identity.'|'.spl_object_id($tree).'|'.spl_object_id($request).'|'.$request->fullUrl().'|'.$context->fingerprint;

        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        $active = new ActiveStateResolver($this->config->matchQueryString);
        $visible = [];
        $best = [null, 0];
        $this->visit($tree->nodes, $tree->requirements, $context, $active, $visible, $best);

        if (count($this->resolved) > 32) {
            $this->resolved = [];
        }

        [$nodes] = $this->build($tree->nodes, $visible, $best[0], $context);
        $trail = $best[0] === null ? [] : $this->trail($nodes, $best[0]);

        return $this->resolved[$key] = new ResolvedNavigation($tree, $context, $nodes, $trail === [] ? null : $trail[array_key_last($trail)], $trail);
    }

    public function context(string $navigation): NavigationContext
    {
        $security = $this->security->context();
        $request = $this->request();
        $route = $request->route();
        $parameters = [];

        foreach (is_object($route) && method_exists($route, 'parameters') ? $route->parameters() : [] as $name => $value) {
            if (is_string($name)) {
                $parameters[$name] = $value;
            }
        }

        return new NavigationContext(
            navigation: $navigation,
            user: $security->user,
            tenant: $security->tenant,
            route: is_object($route) && method_exists($route, 'getName') && is_string($route->getName()) ? $route->getName() : null,
            parameters: $parameters,
            path: ActiveStateResolver::normalize($request->getPathInfo()),
            query: $request->query->all(),
            host: $request->getHost(),
            fingerprint: hash('sha256', $security->fingerprint()),
            security: $this->security,
            data: $this->data,
        );
    }

    /**
     * The page being shown (read each time: one resolver may outlive a request
     * in tests and workers). During a Livewire update the HTTP request is
     * Livewire's endpoint, so the page is rebuilt from Livewire's original
     * URL and its route matched, keeping active states and breadcrumbs right.
     */
    private function request(): Request
    {
        $request = $this->container->make('request');
        $request = $request instanceof Request ? $request : Request::capture();

        if (! class_exists(Livewire::class) || ! Livewire::isLivewireRequest()) {
            return $request;
        }

        $url = Livewire::originalUrl();
        $page = $this->pages[$url] ?? null;

        if ($page !== null) {
            return $page;
        }

        $page = Request::create(is_string($url) ? $url : '/');

        try {
            $route = $this->container->make(Router::class)->getRoutes()->match($page);
            $route->bind($page);
            $page->setRouteResolver(static fn (): Route => $route);
        } catch (Throwable) {
            // Not an application route: no route-based active state.
        }

        return $this->pages[$url] = $page;
    }

    /**
     * Visibility and URLs of every node, and the best active item.
     *
     * @param  list<Node>  $nodes
     * @param  list<Requirement>  $inherited
     * @param  array<string, string|null>  $visible  Visible node ids => URL.
     * @param  array{0: string|null, 1: int}  $best
     */
    private function visit(array $nodes, array $inherited, NavigationContext $context, ActiveStateResolver $active, array &$visible, array &$best): void
    {
        foreach ($nodes as $node) {
            $chain = [...$inherited, ...$node->requirements];

            if ($node->component->isHidden() || ! $this->visibility->allows($chain, $context->fingerprint)) {
                continue;
            }

            $url = null;

            if ($node->component instanceof Item) {
                $url = $this->routes->url($node->component, $context);

                if ($url === null) {
                    continue;
                }

                $score = $active->score($node->component, $url, $context);

                if ($score > $best[1]) {
                    $best = [$node->id, $score];
                }
            } elseif ($node->component instanceof ExternalLink) {
                $url = $node->component->url();

                if ($url === null) {
                    continue;
                }
            }

            $visible[$node->id] = $url;
            $this->visit($node->children, $chain, $context, $active, $visible, $best);
        }
    }

    /**
     * @param  list<Node>  $nodes
     * @param  array<string, string|null>  $visible
     * @return array{0: list<ResolvedNode>, 1: bool} The nodes, and whether one of them is (or holds) the active item.
     */
    private function build(array $nodes, array $visible, ?string $activeId, NavigationContext $context): array
    {
        $resolved = [];
        $holdsActive = false;

        foreach ($nodes as $node) {
            if (! array_key_exists($node->id, $visible)) {
                continue;
            }

            $component = $node->component;

            if ($node->isContainer() && $component instanceof Group) {
                [$children, $descendant] = $this->build($node->children, $visible, $activeId, $context);

                if (array_filter($children, static fn (ResolvedNode $child): bool => $child->isLink() || $child->isContainer()) === []) {
                    continue;
                }

                $collapsible = $component->collapsible() ?? $this->config->collapsibleGroups;
                $resolved[] = new ResolvedNode($node->id, $node->type, $component, $component->label(), activeDescendant: $descendant, expanded: $descendant || ! $collapsible || ! $component->isCollapsed(), collapsible: $collapsible, children: $children, depth: $node->depth);
                $holdsActive = $holdsActive || $descendant;

                continue;
            }

            $isActive = $node->id === $activeId;
            $badge = $component instanceof Item ? $this->badges->resolve($component, $context, $node->id) : null;
            $resolved[] = new ResolvedNode($node->id, $node->type, $component, $component->label(), $visible[$node->id], active: $isActive, badge: $badge, depth: $node->depth);
            $holdsActive = $holdsActive || $isActive;
        }

        return [self::tidy($resolved), $holdsActive];
    }

    /**
     * Drop headings with nothing under them and dividers at the edges or next to each other.
     *
     * @param  list<ResolvedNode>  $nodes
     * @return list<ResolvedNode>
     */
    private static function tidy(array $nodes): array
    {
        $kept = [];

        foreach ($nodes as $index => $node) {
            if ($node->type === 'heading') {
                $next = $nodes[$index + 1] ?? null;

                if ($next === null || $next->type === 'heading' || $next->type === 'divider') {
                    continue;
                }
            }

            if ($node->type === 'divider' && ($kept === [] || $kept[array_key_last($kept)]->type === 'divider')) {
                continue;
            }

            $kept[] = $node;
        }

        while ($kept !== [] && $kept[array_key_last($kept)]->type === 'divider') {
            array_pop($kept);
        }

        return $kept;
    }

    /**
     * The groups holding the active item, then the item.
     *
     * @param  list<ResolvedNode>  $nodes
     * @return list<ResolvedNode>
     */
    private function trail(array $nodes, string $activeId): array
    {
        foreach ($nodes as $node) {
            if ($node->id === $activeId) {
                return [$node];
            }

            if ($node->activeDescendant) {
                return [$node, ...$this->trail($node->children, $activeId)];
            }
        }

        return [];
    }
}
