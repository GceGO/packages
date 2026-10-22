<?php

declare(strict_types=1);

namespace Gcego\Layouts\Rendering;

use Gcego\Chroma\ChromaManager;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Layouts\LayoutComponent;
use Gcego\Layouts\LayoutsConfig;
use Gcego\Layouts\Presets\LayoutPresets;
use Gcego\Rendering\Context\RenderContext;
use Gcego\Rendering\Contracts\RendererInterface;
use Gcego\Rendering\Contracts\StylingResolver;
use Gcego\Rendering\Exceptions\RenderException;
use Gcego\Rendering\Output\RenderResult;
use Gcego\Rendering\Renderers\ComponentRenderer;
use Gcego\Rendering\Styling\StyleSlots;
use Gcego\Security\SecurityManager;
use Gcego\Tables\Support\Icons;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\View\Factory;

/**
 * The gcego/runtime renderer of every layout component.
 *
 * Children render through the runtime (each with its own renderer,
 * authorization and Livewire host) before the layout, so a child that
 * renders nothing (unauthorized, empty) leaves no empty wrapper or grid
 * cell, and a layout left with nothing hides itself. Regions a component
 * owns (tabs, split regions, header actions) are built here and placed by
 * the component's view. Nested layout components with requirements are
 * checked against the registered definition being rendered (inheriting its
 * requirements), or against their own requirements for trusted renders
 * without an identity.
 */
final readonly class LayoutRenderer implements RendererInterface
{
    public function __construct(
        private Factory $views,
        private ComponentRenderer $renderer,
        private StylingResolver $resolver,
        private ChromaManager $styling,
        private LayoutsConfig $config,
        private Icons $icons,
        private Container $container,
    ) {}

    public function render(ComponentInterface $component, RenderContext $context): RenderResult
    {
        if (! $component instanceof LayoutComponent) {
            return RenderResult::empty();
        }

        if (! $context->node->isRoot() && ! $this->allows($component, $context)) {
            return RenderResult::empty();
        }

        $layout = $this->build($component, $context);

        if ($layout->isEmpty() && $component->hidesWhenEmpty() && $component->emptyText() === null) {
            return RenderResult::empty();
        }

        $view = $component->view();

        if (! $this->views->exists($view)) {
            throw RenderException::missingView($view, self::class);
        }

        return RenderResult::view($this->views->make($view, [
            ...$component->viewData($layout),
            'layout' => $layout,
            'component' => $component,
            'context' => $context,
            'presets' => LayoutPresets::class,
            'config' => $this->config,
        ]));
    }

    private function build(LayoutComponent $component, RenderContext $context): LayoutView
    {
        $level = $component->childHeadingLevel($context);
        $children = $this->renderer->children($context, $level);
        $rendered = [];
        $regions = [];

        foreach ($children->nodes() as $index => $node) {
            $child = $node->component;

            if ($child instanceof LayoutComponent && $component->ownsRegion($child)) {
                $regionContext = $context->child($node, $level);

                if (! $this->allows($child, $regionContext)) {
                    continue;
                }

                $region = $this->build($child, $regionContext);

                if (! $region->isEmpty() || ! $child->hidesWhenEmpty()) {
                    $regions[] = $region;
                }

                continue;
            }

            $html = $children->render($index)->toHtml();

            if (trim($html) !== '') {
                $rendered[] = [$html, $child, $node];
            }
        }

        $items = [];

        foreach ($rendered as [$html, $child, $node]) {
            $items[] = new LayoutItem($html, $child, $component->placement($child, count($rendered), $this->config), $context->isLivewire() ? $context->child($node)->domId('item') : null);
        }

        return new LayoutView($component, $context, $items, $regions, $this->config, new StyleSlots($this->resolver, $component, $context), $this->styling, $this->icons);
    }

    private function allows(LayoutComponent $component, RenderContext $context): bool
    {
        if ($component->requirements() === []) {
            return true;
        }

        $security = $this->container->make(SecurityManager::class);

        return ($context->identity !== null ? $security->definition($context->identity, $context->path()) : $security->definition($component))->allowed;
    }
}
