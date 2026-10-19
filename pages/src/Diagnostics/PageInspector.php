<?php

declare(strict_types=1);

namespace Gcego\Pages\Diagnostics;

use Gcego\Actions\Action;
use Gcego\Actions\ActionGroup;
use Gcego\Actions\Resolution\ActionResolver;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Data\Source;
use Gcego\Forms\Form;
use Gcego\Infolists\Infolist;
use Gcego\Pages\Components\PageLink;
use Gcego\Pages\Page;
use Gcego\Pages\PagesConfig;
use Gcego\Pages\Registry\PageDefinitions;
use Gcego\Pages\Routing\PageRouteDefinition;
use Gcego\Pages\Routing\PageRouteResolver;
use Gcego\Pages\Types\FormPage;
use Gcego\Runtime\Rendering\RendererRegistry;
use Gcego\Security\Contracts\Requirement;
use Gcego\Tables\Table;
use Gcego\Widgets\Widget;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Router;
use Throwable;

/**
 * Inspects page definitions without opening them: no authorization, no
 * record lookup, no query, no rendering. Checks the definition, its route
 * (and conflicts with other routes and pages), renderers, authorization and
 * record configuration, actions, links, navigation metadata, styling slots
 * and size. Reports carry paths and names, never record values, so they are
 * safe for logs and future CLI tools.
 */
final readonly class PageInspector
{
    public function __construct(
        private PageDefinitions $pages,
        private PageRouteResolver $routes,
        private RendererRegistry $renderers,
        private Router $router,
        private Gate $gate,
        private PagesConfig $config,
    ) {}

    public function inspect(string|Page $page): PageReport
    {
        $identity = null;

        if (is_string($page)) {
            $identity = $this->pages->identity($page);
            $page = $this->pages->resolve($identity);
        } elseif ($this->pages->has($page->pageName())) {
            $identity = $this->pages->identity($page->pageName());
        }

        $diagnostics = [];
        $components = [];

        foreach ($page->validate()->errors() as $error) {
            $diagnostics[] = new PageDiagnostic('definition', PageDiagnostic::Error, (string) $error);
        }

        $route = PageRouteDefinition::fromPage($page);
        $registered = $identity === null ? [] : $this->routes->routeNames($identity);

        if ($identity !== null && $registered === []) {
            $diagnostics[] = new PageDiagnostic('route.missing', PageDiagnostic::Warning, $this->config->routing ? 'the page has no route yet; routes are registered when the application boots (or by gcego/panels).' : 'gcego-pages.routing.enabled is off and no route was registered for this page.');
        }

        $diagnostics = [...$diagnostics, ...$this->routeConflicts($identity, $page)];
        $hosts = 1;

        foreach ((new ComponentWalker)->preOrder($page) as $node) {
            $component = $node->component;
            $path = $node->path->toString();
            $components[] = [
                'path' => $path,
                'type' => $component->type(),
                'depth' => $node->depth,
                'requirements' => array_values(array_map(static fn (Requirement $requirement): string => $requirement->describe(), array_filter($component->configurations()->all(), static fn (mixed $configuration): bool => $configuration instanceof Requirement))),
            ];

            if (! $component instanceof Source && ! $this->renderers->has($component)) {
                $diagnostics[] = new PageDiagnostic('renderer.missing', PageDiagnostic::Error, sprintf('no gcego/runtime renderer draws %s.', $component::class), $path);
            }

            $hosts += match (true) {
                $component instanceof Table, $component instanceof Form, $component instanceof Infolist => 1,
                $component instanceof Action && ! $component->isHidden() => 1,
                $component instanceof ActionGroup => 1,
                $component instanceof Widget && $component->needsLivewire() => 1,
                default => 0,
            };

            if ($component instanceof PageLink) {
                $diagnostics = [...$diagnostics, ...$this->link($component, $page, $path, $identity)];
            }

            foreach ($component->configurations() as $configuration) {
                if ($configuration instanceof Tailwind && $component instanceof Page) {
                    foreach (array_diff($configuration->slotNames(), array_keys(Page::styleSlots())) as $slot) {
                        $diagnostics[] = new PageDiagnostic('styling.slot', PageDiagnostic::Error, sprintf('pages have no "%s" styling slot (use %s).', $slot, implode(', ', array_keys(Page::styleSlots()))), $path);
                    }
                }
            }
        }

        $diagnostics = [...$diagnostics, ...$this->authorization($page), ...$this->record($page), ...$this->actions($identity, $page), ...$this->navigation($page)];

        if ($page->parentName() !== null && ! $this->pages->has($page->parentName())) {
            $diagnostics[] = new PageDiagnostic('breadcrumbs.parent', PageDiagnostic::Warning, sprintf('Parent "%s" is not a registered page; breadcrumbs start at this page.', $page->parentName()));
        }

        if (count($components) > $this->config->maxComponents) {
            $diagnostics[] = new PageDiagnostic('performance.components', PageDiagnostic::Warning, sprintf('the page has %d components (more than %d); every request walks and validates them.', count($components), $this->config->maxComponents));
        }

        if ($hosts > $this->config->maxLivewireHosts) {
            $diagnostics[] = new PageDiagnostic('performance.livewire', PageDiagnostic::Warning, sprintf('the page mounts about %d Livewire components (more than %d); group actions or split the page.', $hosts, $this->config->maxLivewireHosts));
        }

        return new PageReport($identity, $page::pageType(), [
            'path' => $route->path,
            'name' => $this->config->namePrefix.$route->route,
            'registered' => $registered,
            'record' => $route->record,
            'record_key' => $route->record ? $page->recordKey($this->config->recordKey) : null,
            'middleware' => [...$this->config->middleware, ...$route->middleware],
        ], $components, $diagnostics);
    }

    /**
     * @return list<PageReport>
     */
    public function inspectAll(): array
    {
        return array_map(fn ($descriptor): PageReport => $this->inspect($descriptor->key()), $this->pages->descriptors());
    }

    /**
     * Another route (or page) with this page's route name or URL.
     *
     * @return list<PageDiagnostic>
     */
    private function routeConflicts(?string $identity, Page $page): array
    {
        $name = $this->config->namePrefix.$page->routeName();
        $uri = self::uriKey(trim($this->config->prefix.'/'.$page->path(), '/'));
        $diagnostics = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            if ($identity !== null && PageRouteResolver::pageOf($route) === $identity) {
                continue;
            }

            if ($route->getName() === $name) {
                $diagnostics[] = new PageDiagnostic('route.conflict', PageDiagnostic::Error, sprintf('route name "%s" is already used by %s.', $name, PageRouteResolver::pageOf($route) ?? 'route "'.$route->uri().'"'));
            }

            if (in_array('GET', $route->methods(), true) && self::uriKey($route->uri()) === $uri) {
                $diagnostics[] = new PageDiagnostic('route.conflict', PageDiagnostic::Error, sprintf('GET /%s is already answered by %s.', trim($route->uri(), '/'), PageRouteResolver::pageOf($route) ?? 'route "'.($route->getName() ?? $route->uri()).'"'));
            }
        }

        foreach ($this->pages->descriptors() as $descriptor) {
            if ($descriptor->key() === $identity) {
                continue;
            }

            try {
                $other = PageRouteDefinition::fromDescriptor($descriptor);
            } catch (Throwable) {
                continue;
            }

            if (self::uriKey(trim($this->config->prefix.'/'.$other->path, '/')) === $uri || $other->route === $page->routeName()) {
                $diagnostics[] = new PageDiagnostic('route.conflict', PageDiagnostic::Error, sprintf('%s has the same route name or URL.', $descriptor->key()));
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<PageDiagnostic>
     */
    private function authorization(Page $page): array
    {
        foreach ($page->configurations() as $configuration) {
            if ($configuration instanceof Requirement) {
                return [];
            }
        }

        return [new PageDiagnostic('authorization.default', $this->config->defaultAccess === 'deny' ? PageDiagnostic::Error : PageDiagnostic::Warning, match ($this->config->defaultAccess) {
            'deny' => 'the page has no requirement, so nobody may open it (gcego-pages.authorization.default is "deny"); add a Permission, Ability, Authenticated or PublicAccess.',
            'public' => 'the page has no requirement and gcego-pages.authorization.default is "public": everyone, guests included, may open it.',
            default => 'the page has no requirement: every signed-in user may open it (gcego-pages.authorization.default is "authenticated").',
        })];
    }

    /**
     * @return list<PageDiagnostic>
     */
    private function record(Page $page): array
    {
        if (! $page->requiresRecord()) {
            return [];
        }

        $source = $page->source();
        $model = $source?->get(Source::Model);

        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            return [];
        }

        $requirements = $source === null ? [] : array_filter($source->configurations()->all(), static fn (mixed $node): bool => $node instanceof Requirement);

        if ($this->gate->getPolicyFor($model) === null && ! $this->gate->has($page->recordAbility()) && $requirements === []) {
            return [new PageDiagnostic('record.policy', PageDiagnostic::Error, sprintf('%s has no policy (and the Source no requirement), so every record is denied ("%s").', $model, $page->recordAbility()))];
        }

        return [];
    }

    /**
     * @return list<PageDiagnostic>
     */
    private function actions(?string $identity, Page $page): array
    {
        try {
            $actions = ActionResolver::collect($identity ?? 'page:'.$page->registryName(), $page);
        } catch (Throwable $exception) {
            return [new PageDiagnostic('actions.invalid', PageDiagnostic::Error, $exception->getMessage())];
        }

        if ($page instanceof FormPage) {
            foreach ($actions as $action) {
                if ($action->name() === $page::formAction()) {
                    return [];
                }
            }

            return [new PageDiagnostic('actions.form', PageDiagnostic::Error, sprintf('the form has no "%s" action to submit through.', $page::formAction()))];
        }

        return [];
    }

    /**
     * @return list<PageDiagnostic>
     */
    private function navigation(Page $page): array
    {
        if (! $page->navigation()['enabled']) {
            return [];
        }

        if ($page->requiresRecord()) {
            return [new PageDiagnostic('navigation.unreachable', PageDiagnostic::Warning, 'record pages are never added to navigation (they need a record).')];
        }

        return [];
    }

    /**
     * @return list<PageDiagnostic>
     */
    private function link(PageLink $link, Page $page, string $path, ?string $identity): array
    {
        if (! $this->pages->has($link->target(), $identity)) {
            return [new PageDiagnostic('link.target', PageDiagnostic::Error, sprintf('PageLink to "%s": no such page.', $link->target()), $path)];
        }

        $target = $this->pages->resolve($link->target(), $identity);

        if ($target->requiresRecord() && ! $page->requiresRecord() && $link->passesRecord() !== false) {
            return [new PageDiagnostic('link.record', PageDiagnostic::Warning, sprintf('PageLink to record page "%s" from a page without a record: it is never shown.', $link->target()), $path)];
        }

        return [];
    }

    private static function uriKey(string $uri): string
    {
        return (string) preg_replace('/\{[^}]+\}/', '{}', trim($uri, '/'));
    }
}
