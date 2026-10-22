<?php

declare(strict_types=1);

namespace Gcego\Pages;

use Closure;
use Gcego\Pages\Context\PageContext;
use Gcego\Pages\Contracts\PageLifecycleHook;
use Gcego\Pages\Diagnostics\PageInspector;
use Gcego\Pages\Diagnostics\PageReport;
use Gcego\Pages\Lifecycle\PageHooks;
use Gcego\Pages\Lifecycle\PageLifecycle;
use Gcego\Pages\Lifecycle\PageStage;
use Gcego\Pages\Registry\PageDefinitions;
use Gcego\Pages\Rendering\PageRenderContext;
use Gcego\Pages\Routing\PageRouteDefinition;
use Gcego\Pages\Routing\PageRouteRegistrar;
use Gcego\Pages\Routing\PageRouteResolver;
use Gcego\Registry\Index\DefinitionDescriptor;
use Gcego\Rendering\Context\RenderMode;
use Gcego\Rendering\Facades\Rendering;
use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\HtmlString;

/**
 * The entry point of gcego/pages (a singleton; request-scoped services are
 * resolved per call, so it is safe in long-running workers):
 *
 *     $pages = app(PageManager::class);
 *     $context = $pages->open('subjects.edit', record: 7);     // resolve, authorize page and record, prepare header
 *     echo $pages->render('subjects.edit', record: 7);           // the page as HTML (children mount their own hosts)
 *     $pages->url('subjects.edit', 7);                            // "/admin/subjects/7/edit"
 *     $pages->inspect('subjects.edit');                           // diagnostics, nothing executed
 *
 * Pages are looked up by name or "page:" identity through gcego/registry
 * only; a request can never name a class.
 */
final readonly class PageManager
{
    public function __construct(
        private Container $container,
        private PagesConfig $config,
        private PageDefinitions $pages,
        private PageHooks $hooks,
    ) {}

    /**
     * "subjects.create" or "page:subjects-create" -> "page:subjects-create".
     */
    public function identity(string $page): string
    {
        return $this->pages->identity($page);
    }

    public function has(string $page): bool
    {
        return $this->pages->has($page);
    }

    /**
     * A registered page (404 when unknown). Loads the definition; never authorizes.
     */
    public function resolve(string $page): Page
    {
        return $this->pages->resolve($page);
    }

    /**
     * Open a page for the current user: the lifecycle up to Prepared (authorization and record included).
     *
     * @param  array<array-key, mixed>  $parameters  Route parameters other than {record} (untrusted).
     * @param  array<array-key, mixed>  $state  Page state such as ['tab' => 'results'] (untrusted).
     */
    public function open(string $page, string|int|null $record = null, array $parameters = [], array $state = []): PageContext
    {
        return $this->container->make(PageLifecycle::class)->open($page, $record, $parameters, $state);
    }

    /**
     * Open and render a page as HTML (static: the page itself is not a Livewire component; its parts mount their own hosts).
     *
     * @param  array<array-key, mixed>  $parameters
     * @param  array<array-key, mixed>  $state
     */
    public function render(string $page, string|int|null $record = null, array $parameters = [], array $state = []): HtmlString
    {
        return $this->renderContext($this->open($page, $record, $parameters, $state));
    }

    /**
     * Render an open page (PageHost and PageController use this).
     */
    public function renderContext(PageContext $context, bool $live = false): HtmlString
    {
        $lifecycle = $this->container->make(PageLifecycle::class);
        $lifecycle->fire(PageStage::Rendering, $context->identity, $context->page, $context);

        $html = $this->container->make(PageRenderContext::class)->run($context, fn (): string => Rendering::render(
            $context->state->apply($context->page),
            $live ? RenderMode::Livewire : RenderMode::Static,
            identity: $context->identity,
            metadata: $context->hasRecord() ? ['record' => $context->recordKey()] : [],
        )->toHtml());

        $lifecycle->fire(PageStage::Rendered, $context->identity, $context->page, $context);
        $lifecycle->fire(PageStage::Completed, $context->identity, $context->page, $context);

        return new HtmlString($html);
    }

    /**
     * The URL of a page's route (root-relative), or null without one. Not an authorization check.
     *
     * @param  array<string, string|int>  $parameters
     */
    public function url(string $page, string|int|null $record = null, array $parameters = []): ?string
    {
        return $this->container->make(PageRouteResolver::class)->url($this->identity($page), $record, $parameters);
    }

    /**
     * Register page routes (see PageRouteRegistrar::register()).
     *
     * @param  list<string>|null  $middleware
     * @param  (Closure(PageRouteDefinition, DefinitionDescriptor): bool)|null  $filter
     * @return list<Route>
     */
    public function routes(?string $prefix = null, ?string $namePrefix = null, ?array $middleware = null, ?string $domain = null, ?Closure $filter = null): array
    {
        return $this->container->make(PageRouteRegistrar::class)->register($this->container->make(Router::class), $prefix, $namePrefix, $middleware, $domain, $filter);
    }

    /**
     * @param  class-string<PageLifecycleHook>  $hook
     */
    public function hook(string $hook): void
    {
        $this->hooks->add($hook);
    }

    public function inspect(string|Page $page): PageReport
    {
        return $this->container->make(PageInspector::class)->inspect($page);
    }

    /**
     * Every registered page, plus route conflicts between them.
     *
     * @return list<PageReport>
     */
    public function inspectAll(): array
    {
        return $this->container->make(PageInspector::class)->inspectAll();
    }

    public function config(): PagesConfig
    {
        return $this->config;
    }
}
