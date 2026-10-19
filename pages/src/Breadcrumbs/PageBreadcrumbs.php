<?php

declare(strict_types=1);

namespace Gcego\Pages\Breadcrumbs;

use Gcego\Navigation\Breadcrumbs\Breadcrumb;
use Gcego\Navigation\Breadcrumbs\BreadcrumbCollection;
use Gcego\Navigation\NavigationManager;
use Gcego\Pages\Authorization\PageAuthorizer;
use Gcego\Pages\Context\PageContext;
use Gcego\Pages\Context\RecordPageContext;
use Gcego\Pages\Exceptions\PageNotFoundException;
use Gcego\Pages\Page;
use Gcego\Pages\PagesConfig;
use Gcego\Pages\Registry\PageDefinitions;
use Gcego\Pages\Routing\PageRouteResolver;
use Gcego\Pages\Types\ViewPage;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * The breadcrumb trail of an open page, as gcego/navigation Breadcrumbs:
 *
 *     Subjects › Chemistry › Edit
 *
 *  - Ancestors: the page's Parent, else its name without the last segment
 *    ("subjects.edit" -> "subjects") when that page exists, up to 5 levels.
 *    With gcego-pages.breadcrumbs.navigation the trail starts from that
 *    navigation's active trail instead (groups included).
 *  - Record pages add the record (its label, linking to the record's view
 *    page when there is one).
 *  - A crumb links only when the user may open that page (and record);
 *    otherwise it is plain text, so a trail never leads to a 403.
 *  - The last crumb is the current page.
 */
final readonly class PageBreadcrumbs
{
    private const int MaxAncestors = 5;

    public function __construct(
        private PageDefinitions $pages,
        private PageRouteResolver $routes,
        private PageAuthorizer $authorizer,
        private PagesConfig $config,
        private Container $container,
    ) {}

    public function for(PageContext $context, string $title): BreadcrumbCollection
    {
        if (! $this->config->breadcrumbs || ! $context->page->showsBreadcrumbs()) {
            return new BreadcrumbCollection;
        }

        $crumbs = $this->config->breadcrumbNavigation === null ? $this->ancestors($context) : $this->navigation($context);
        $crumbs[] = new Breadcrumb($title, $this->routes->url($context->identity, $context->recordKey(), $context->parameters), current: true);

        return new BreadcrumbCollection(...$crumbs);
    }

    /**
     * @return list<Breadcrumb>
     */
    private function ancestors(PageContext $context): array
    {
        $chain = [];
        $seen = [$context->page->pageName() => true];
        $name = $this->parentOf($context->page, $context->identity);

        while ($name !== null && count($chain) < self::MaxAncestors && ! isset($seen[$name])) {
            try {
                $page = $this->pages->resolve($name, $context->identity);
            } catch (PageNotFoundException) {
                break;
            }

            $seen[$name] = true;
            array_unshift($chain, $page);
            $name = $this->parentOf($page, $context->identity);
        }

        $home = $this->config->breadcrumbHome;

        if ($home !== null && ! isset($seen[$home]) && $this->pages->has($home, $context->identity)) {
            array_unshift($chain, $this->pages->resolve($home, $context->identity));
        }

        $crumbs = [];
        $recordShown = false;

        foreach ($chain as $page) {
            $crumb = $this->crumb($page, $context);

            if ($crumb !== null) {
                $crumbs[] = $crumb;
                $recordShown = $recordShown || $page->requiresRecord();
            }
        }

        if ($context instanceof RecordPageContext && ! $recordShown && ! $context->page instanceof ViewPage) {
            $crumbs[] = $this->recordCrumb($context);
        }

        return $crumbs;
    }

    /**
     * @return list<Breadcrumb>
     */
    private function navigation(PageContext $context): array
    {
        try {
            $trail = $this->container->make(NavigationManager::class)->breadcrumbs((string) $this->config->breadcrumbNavigation);
        } catch (Throwable) {
            return $context instanceof RecordPageContext && ! $context->page instanceof ViewPage ? [$this->recordCrumb($context)] : [];
        }

        $current = $this->routes->url($context->identity, $context->recordKey(), $context->parameters);
        $crumbs = [];

        foreach ($trail as $crumb) {
            if ($crumb->url === null || $crumb->url !== $current) {
                $crumbs[] = $crumb->asCurrent(false);
            }
        }

        if ($context instanceof RecordPageContext && ! $context->page instanceof ViewPage) {
            $crumbs[] = $this->recordCrumb($context);
        }

        return $crumbs;
    }

    private function crumb(Page $page, PageContext $context): ?Breadcrumb
    {
        $identity = $this->pages->identity($page->pageName(), $context->identity);

        if ($page->requiresRecord()) {
            if (! $context instanceof RecordPageContext) {
                return null;
            }

            [$decision] = $this->authorizer->record($identity, $page, $context->key);
            $allowed = $this->authorizer->allows($identity, $page) && $decision->allowed;

            return new Breadcrumb($context->label ?? '#'.$context->key, $allowed ? $this->routes->url($identity, $context->key, $this->parametersFor($page, $context)) : null);
        }

        $url = $this->authorizer->allows($identity, $page) ? $this->routes->url($identity, null, $this->parametersFor($page, $context)) : null;

        return new Breadcrumb((string) __($page->pageTitle(), ['record' => '']), $url);
    }

    private function recordCrumb(RecordPageContext $context): Breadcrumb
    {
        $label = $context->label ?? '#'.$context->key;
        $segments = explode('.', $context->page->pageName());
        array_pop($segments);

        foreach (['view', 'show'] as $segment) {
            $name = implode('.', [...$segments, $segment]);

            if ($name === $context->page->pageName() || ! $this->pages->has($name, $context->identity)) {
                continue;
            }

            $view = $this->pages->resolve($name, $context->identity);
            $identity = $this->pages->identity($name, $context->identity);

            if (! $view->requiresRecord()) {
                continue;
            }

            [$decision] = $this->authorizer->record($identity, $view, $context->key);

            return new Breadcrumb($label, $decision->allowed && $this->authorizer->allows($identity, $view) ? $this->routes->url($identity, $context->key, $this->parametersFor($view, $context)) : null);
        }

        return new Breadcrumb($label);
    }

    /**
     * The parent page name: Parent, else the name without its last segment when that page exists.
     */
    private function parentOf(Page $page, string $relativeTo): ?string
    {
        $parent = $page->parentName();

        if ($parent !== null) {
            return $parent;
        }

        $segments = explode('.', $page->pageName());

        if (count($segments) < 2) {
            return null;
        }

        array_pop($segments);
        $name = implode('.', $segments);

        return $this->pages->has($name, $relativeTo) ? $name : null;
    }

    /**
     * The current page's route parameters an ancestor needs (an {institution} shared along the trail).
     *
     * @return array<string, string>
     */
    private function parametersFor(Page $page, PageContext $context): array
    {
        return array_intersect_key($context->parameters, array_flip($page->parameters()));
    }
}
