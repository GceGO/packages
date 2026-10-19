<?php

declare(strict_types=1);

namespace Gcego\Pages\Rendering;

use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Pages\Authorization\PageAuthorizer;
use Gcego\Pages\Components\PageLink;
use Gcego\Pages\PagesConfig;
use Gcego\Pages\Presets\PagePresets;
use Gcego\Pages\Records\RecordKey;
use Gcego\Pages\Registry\PageDefinitions;
use Gcego\Pages\Routing\PageRouteResolver;
use Gcego\Runtime\Contracts\RendererInterface;
use Gcego\Runtime\Contracts\StylingResolver;
use Gcego\Runtime\Rendering\RenderContext;
use Gcego\Runtime\Rendering\RenderResult;
use Gcego\Security\SecurityManager;
use Gcego\Tables\Support\Icons;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\View\Factory;

/**
 * Renders a PageLink as a button-styled link, or nothing when the target
 * page does not exist, has no route, or the user may not open it (for a
 * record page: that record). The record and route parameters come from the
 * open page (server side), never from the browser.
 */
final readonly class PageLinkRenderer implements RendererInterface
{
    public function __construct(
        private Factory $views,
        private StylingResolver $resolver,
        private ChromaManager $styling,
        private Icons $icons,
        private PageRenderContext $frames,
        private PageRouteResolver $routes,
        private PageDefinitions $pages,
        private PagesConfig $config,
        private Container $container,
    ) {}

    public function render(ComponentInterface $component, RenderContext $context): RenderResult
    {
        $url = $component instanceof PageLink ? $this->url($component) : null;

        if (! $component instanceof PageLink || $url === null) {
            return RenderResult::empty();
        }

        $button = $this->styling->merge($this->styling->preset('control.button'), $this->styling->preset($component->isPrimary() ? 'control.button.primary' : 'control.button.secondary'), implode(' ', PagePresets::Link));
        $own = $this->resolver->classes($component, Tailwind::Wrapper, $context);
        $iconClass = $this->resolver->classes($component, Tailwind::Icon, $context);

        return RenderResult::view($this->views->make('gcego-pages::components.link', [
            'url' => $url,
            'label' => (string) __($component->label()),
            'icon' => $component->icon() === null ? null : $this->icons->svg($component->icon(), $iconClass === '' ? implode(' ', PagePresets::LinkIcon) : $this->styling->merge(implode(' ', PagePresets::LinkIcon), $iconClass)),
            'class' => $own === '' ? $button : $this->styling->merge($button, $own),
            'navigate' => $this->config->wireNavigate,
        ]));
    }

    private function url(PageLink $link): ?string
    {
        $page = $this->frames->current();

        if (! $this->pages->has($link->target(), $page?->identity)) {
            return null;
        }

        $identity = $this->pages->identity($link->target(), $page?->identity);
        $target = $this->pages->resolve($identity);
        $authorizer = $this->container->make(PageAuthorizer::class);

        if (! $authorizer->allows($identity, $target)) {
            return null;
        }

        $parameters = $page === null ? [] : array_intersect_key($page->parameters, array_flip($target->parameters()));
        $tenant = $page !== null && $page->tenant !== null ? $page->tenant : $this->tenant();

        foreach ($link->parameters() as $name => $value) {
            if ($value === ':tenant') {
                if ($tenant === null) {
                    return null;
                }

                $value = $tenant;
            }

            if ($value === ':record') {
                $value = $page?->recordKey();

                if ($value === null) {
                    return null;
                }
            }

            $parameters[$name] = (string) $value;
        }

        $record = null;

        if ($target->requiresRecord() && $link->passesRecord() !== false) {
            $record = $page?->recordKey();

            if ($record === null || ! RecordKey::valid($target->recordKey($this->config->recordKey), $record) || ! $authorizer->record($identity, $target, $record)[0]->allowed) {
                return null;
            }
        }

        return $this->routes->url($identity, $record, $parameters);
    }

    private function tenant(): ?string
    {
        $tenant = $this->container->make(SecurityManager::class)->context()->tenant;

        return $tenant === null ? null : (string) $tenant->key;
    }
}
