<?php

declare(strict_types=1);

namespace Gcego\Navigation;

use Gcego\Navigation\Badges\BadgeResolver;
use Gcego\Navigation\Breadcrumbs\BreadcrumbCollection;
use Gcego\Navigation\Breadcrumbs\BreadcrumbResolver;
use Gcego\Navigation\Contracts\MultipleNavigationsContributor;
use Gcego\Navigation\Contracts\NavigationContributor;
use Gcego\Navigation\Diagnostics\NavigationInspector;
use Gcego\Navigation\Diagnostics\NavigationReport;
use Gcego\Navigation\Exceptions\InvalidNavigationException;
use Gcego\Navigation\Registry\NavigationKind;
use Gcego\Navigation\Resolution\NavigationResolver;
use Gcego\Navigation\Resolution\ResolvedNavigation;
use Gcego\Navigation\Tree\NavigationTree;
use Gcego\Navigation\Tree\NavigationTreeBuilder;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Runtime\Rendering\RenderMode;
use Gcego\Runtime\Runtime;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use WeakMap;

/**
 * Entry point for navigations:
 *
 *     app(NavigationManager::class)->render('admin');                  // sidebar
 *     app(NavigationManager::class)->render('admin', 'topbar');        // or mobile, breadcrumbs
 *     app(NavigationManager::class)->breadcrumbs('admin');             // BreadcrumbCollection
 *     app(NavigationManager::class)->resolved('admin');                // what the current user sees
 *
 * Structure (NavigationTree) is built once per definition and process;
 * everything user-, tenant- or request-specific is resolved per request.
 * Only registered identities are rendered by name (unknown ones are 404).
 */
final class NavigationManager
{
    /** @var WeakMap<Navigation, array<string, NavigationTree>> */
    private WeakMap $trees;

    /** @var list<class-string<NavigationContributor>> */
    private array $contributors;

    /** @var array<class-string<NavigationContributor>, NavigationContributor> */
    private array $resolvedContributors = [];

    public function __construct(private readonly Container $container, private readonly NavigationConfig $config)
    {
        $this->trees = new WeakMap;
        $this->contributors = $config->contributors;
    }

    /**
     * The canonical identity ("navigation:admin") of a name or identity.
     */
    public function identity(string $identity): string
    {
        $identity = str_contains($identity, ':') ? $identity : NavigationKind::Id.':'.$identity;

        if (preg_match('/^navigation:[a-z][a-z0-9_-]{0,63}$/', $identity) !== 1) {
            throw new NotFoundHttpException('Navigation not found.');
        }

        return $identity;
    }

    public function resolve(string $identity): Navigation
    {
        try {
            $navigation = $this->container->make(RegistryInterface::class)->get($this->identity($identity));
        } catch (NotFoundHttpException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new NotFoundHttpException('Navigation not found.');
        }

        return $navigation instanceof Navigation ? $navigation : throw new NotFoundHttpException('Navigation not found.');
    }

    /**
     * Add a contributor (trusted code: modules, pages, panels). Call it while
     * booting, before navigations are first built.
     *
     * @param  class-string<NavigationContributor>  $contributor
     */
    public function contribute(string $contributor): void
    {
        if (! is_subclass_of($contributor, NavigationContributor::class)) {
            throw InvalidNavigationException::invalid('Contributor '.$contributor, 'it must implement '.NavigationContributor::class.'.');
        }

        if (! in_array($contributor, $this->contributors, true)) {
            $this->contributors[] = $contributor;
            $this->trees = new WeakMap;
        }
    }

    /**
     * The structure of a navigation with its contributions (cached per definition).
     */
    public function tree(Navigation|string $navigation, ?string $identity = null): NavigationTree
    {
        if (is_string($navigation)) {
            $identity = $this->identity($navigation);
            $navigation = $this->resolve($identity);
        }

        $identity ??= NavigationKind::Id.':'.($navigation->navigationName() !== '' ? $navigation->navigationName() : 'inline');
        $trees = $this->trees[$navigation] ?? [];

        if (! isset($trees[$identity])) {
            $trees[$identity] = (new NavigationTreeBuilder)->build($navigation, $identity, $this->config->maxDepth, $this->contributions($identity));
            $this->trees[$navigation] = $trees;
        }

        return $trees[$identity];
    }

    /**
     * What the current user sees on the current page.
     */
    public function resolved(Navigation|string $navigation, ?string $identity = null): ResolvedNavigation
    {
        return $this->container->make(NavigationResolver::class)->resolve($this->tree($navigation, $identity));
    }

    /**
     * Render a registered navigation: sidebar (default), topbar, mobile or breadcrumbs.
     */
    public function render(string $identity, ?string $display = null): HtmlString
    {
        $navigation = $this->resolve($identity);

        return $this->renderNavigation($navigation, $this->identity($identity), $display);
    }

    /**
     * Render a navigation definition (trusted server code).
     */
    public function renderNavigation(Navigation $navigation, ?string $identity = null, ?string $display = null): HtmlString
    {
        if ($display !== null && ! in_array($display, Navigation::Displays, true)) {
            throw InvalidNavigationException::invalid('Display "'.$display.'"', 'use one of '.implode(', ', Navigation::Displays).'.');
        }

        return new HtmlString($this->container->make(Runtime::class)->render(
            $navigation,
            RenderMode::Static,
            identity: $identity,
            metadata: $display === null ? [] : ['navigation_display' => $display],
        )->toHtml());
    }

    public function breadcrumbs(Navigation|string $navigation, ?string $identity = null): BreadcrumbCollection
    {
        if (! $this->config->breadcrumbs) {
            return new BreadcrumbCollection;
        }

        return $this->container->make(BreadcrumbResolver::class)->resolve($this->resolved($navigation, $identity));
    }

    /**
     * Invalidate every cached badge (Item::BadgeTtl) of a navigation.
     */
    public function forgetBadges(string $identity): void
    {
        $this->container->make(BadgeResolver::class)->forget($this->identity($identity));
    }

    public function inspect(Navigation|string $navigation): NavigationReport
    {
        return $this->container->make(NavigationInspector::class)->inspect($navigation);
    }

    public function config(): NavigationConfig
    {
        return $this->config;
    }

    /**
     * @param  class-string<NavigationContributor>  $class
     */
    private function contributor(string $class): NavigationContributor
    {
        $contributor = $this->container->make($class);

        return $contributor instanceof NavigationContributor ? $contributor : throw InvalidNavigationException::invalid('Contributor '.$class, 'it must implement '.NavigationContributor::class.'.');
    }

    /**
     * @return list<list<NavigationNode>>
     */
    private function contributions(string $identity): array
    {
        $contributions = [];

        foreach ($this->contributors as $class) {
            $contributor = $this->resolvedContributors[$class] ??= $this->contributor($class);

            if ($contributor instanceof MultipleNavigationsContributor) {
                if ($contributor->contributesTo($identity)) {
                    $nodes = [];

                    foreach ($contributor->nodesFor($identity) as $node) {
                        $nodes[] = $node instanceof NavigationNode ? $node : throw InvalidNavigationException::invalid('Contributor '.$class, 'nodesFor() must yield navigation nodes.');
                    }

                    $contributions[] = $nodes;
                }

                continue;
            }

            try {
                $target = $this->identity($contributor->navigation());
            } catch (NotFoundHttpException) {
                throw InvalidNavigationException::invalid('Contributor '.$class, 'navigation() must return a navigation name such as "admin".');
            }

            if ($target === $identity) {
                $nodes = [];

                foreach ($contributor->nodes() as $node) {
                    $nodes[] = $node instanceof NavigationNode ? $node : throw InvalidNavigationException::invalid('Contributor '.$class, 'nodes() must yield navigation nodes.');
                }

                $contributions[] = $nodes;
            }
        }

        return $contributions;
    }
}
