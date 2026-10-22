<?php

declare(strict_types=1);

namespace Gcego\Pages\Rendering;

use Gcego\Actions\Action;
use Gcego\Actions\ActionGroup;
use Gcego\Chroma\ChromaManager;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Data\Source;
use Gcego\Pages\Authorization\PageAuthorizer;
use Gcego\Pages\Context\PageContext;
use Gcego\Pages\Exceptions\PageRenderingException;
use Gcego\Pages\Lifecycle\PageLifecycle;
use Gcego\Pages\Page;
use Gcego\Pages\PagesConfig;
use Gcego\Rendering\Context\RenderContext;
use Gcego\Rendering\Contracts\RendererInterface;
use Gcego\Rendering\Contracts\StylingResolver;
use Gcego\Rendering\Output\RenderResult;
use Gcego\Rendering\Renderers\ComponentRenderer;
use Gcego\Rendering\Styling\StyleSlots;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\HtmlString;

/**
 * The gcego/runtime renderer of every Page: a landmark-free page region
 * (the layout owns <main>) with a header (breadcrumbs, h1 title, icon,
 * description, header actions and links) and a body of the page's other
 * components, each drawn by its own renderer (tables, forms, infolists and
 * actions mount their own Livewire hosts with the page identity and the
 * record key; layouts and widgets authorize themselves).
 *
 * Children with requirements of their own that deny the user are left out,
 * so one restricted section never turns the page into a 403. The Source is
 * data, not content. Actions not placed in the header belong to the table
 * or form that uses them. When nothing is left, the page shows its empty state.
 *
 * Stateless (renderers are shared): request-scoped services (lifecycle,
 * authorizer) are resolved for each render.
 *
 * A page renders only through its registry identity: PageManager (or a
 * page route / PageHost) opens and authorizes it first; rendered by
 * identity through gcego/runtime directly, it is opened here.
 */
final readonly class PageRenderer implements RendererInterface
{
    public function __construct(
        private Factory $views,
        private ComponentRenderer $renderer,
        private StylingResolver $resolver,
        private ChromaManager $styling,
        private PageRenderContext $frames,
        private PagesConfig $config,
        private Container $container,
    ) {}

    public function render(ComponentInterface $component, RenderContext $context): RenderResult
    {
        if (! $component instanceof Page) {
            return RenderResult::empty();
        }

        $page = $this->frames->current();

        if ($page === null || $page->identity !== $context->identity) {
            if ($context->identity === null || ! str_starts_with($context->identity, 'page:') || ! $context->node->isRoot()) {
                throw PageRenderingException::unregistered($component->pageName());
            }

            $record = $context->metadata['record'] ?? null;
            $page = $this->container->make(PageLifecycle::class)->open($context->identity, is_int($record) || is_string($record) ? $record : null);

            return RenderResult::trustedHtml($this->frames->run($page, fn (): string => $this->draw($component, $context, $page)));
        }

        return RenderResult::trustedHtml($this->draw($component, $context, $page));
    }

    private function draw(Page $component, RenderContext $context, PageContext $page): string
    {
        $authorizer = $this->container->make(PageAuthorizer::class);
        $children = $this->renderer->children($context, min(6, $context->headingLevel + 1));
        $headerPaths = $page->header === null ? [] : $page->header->actions;
        $actions = [];
        $body = [];

        foreach ($children->nodes() as $index => $node) {
            $child = $node->component;
            $path = $node->path->toString();

            if ($child instanceof Source || ! $authorizer->allowsChild($page->identity, $page->page, $path)) {
                continue;
            }

            if (in_array($path, $headerPaths, true)) {
                $html = trim($children->render($index)->toHtml());

                if ($html !== '') {
                    $actions[] = new HtmlString($html);
                }

                continue;
            }

            if ($child instanceof Action || $child instanceof ActionGroup) {
                continue;
            }

            $html = trim($children->render($index)->toHtml());

            if ($html !== '') {
                $body[] = new HtmlString($html);
            }
        }

        return $this->views->make('gcego-pages::pages.page', [
            'page' => new PageView($page, $context, $actions, $body, $component->width() ?? $this->config->defaultWidth, new StyleSlots($this->resolver, $component, $context), $this->styling),
            'level' => $context->headingLevel,
        ])->render();
    }
}
