<?php

declare(strict_types=1);

namespace Gcego\Navigation\Rendering;

use Gcego\Chroma\ChromaManager;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Navigation\Navigation;
use Gcego\Navigation\NavigationConfig;
use Gcego\Navigation\NavigationManager;
use Gcego\Navigation\Presets\NavigationPresets;
use Gcego\Rendering\Context\RenderContext;
use Gcego\Rendering\Contracts\RendererInterface;
use Gcego\Rendering\Contracts\StylingResolver;
use Gcego\Rendering\Exceptions\RenderException;
use Gcego\Rendering\Output\RenderResult;
use Gcego\Rendering\Styling\StyleSlots;
use Gcego\Tables\Support\Icons;
use Illuminate\Contracts\View\Factory;

/**
 * The gcego/runtime renderer of Navigation: resolves it for the current
 * request and renders the display asked for (render metadata
 * "navigation_display", else Navigation::Display): sidebar, topbar, mobile
 * or breadcrumbs. A navigation the user can see nothing of renders nothing.
 */
final readonly class NavigationRenderer implements RendererInterface
{
    public function __construct(
        private Factory $views,
        private StylingResolver $resolver,
        private ChromaManager $styling,
        private Icons $icons,
        private NavigationManager $navigations,
        private NavigationConfig $config,
    ) {}

    public function render(ComponentInterface $component, RenderContext $context): RenderResult
    {
        if (! $component instanceof Navigation) {
            return RenderResult::empty();
        }

        $display = $context->metadata['navigation_display'] ?? null;
        $display = is_string($display) && in_array($display, Navigation::Displays, true) ? $display : $component->display();
        $identity = $context->identity !== null && str_starts_with($context->identity, 'navigation:') ? $context->identity : null;
        $resolved = $this->navigations->resolved($component, $identity);
        $crumbs = $display === 'breadcrumbs' ? $this->navigations->breadcrumbs($component, $identity) : null;

        if ($resolved->isEmpty() || $crumbs?->isEmpty() === true) {
            return RenderResult::empty();
        }

        $view = 'gcego-navigation::navigation.'.$display;

        if (! $this->views->exists($view)) {
            throw RenderException::missingView($view, self::class);
        }

        return RenderResult::view($this->views->make($view, [
            'nav' => new NavigationView($resolved, $context, $this->config, new StyleSlots($this->resolver, $component, $context), $this->styling, $this->icons),
            'crumbs' => $crumbs,
            'presets' => NavigationPresets::class,
            'config' => $this->config,
        ]));
    }
}
