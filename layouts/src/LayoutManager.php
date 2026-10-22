<?php

declare(strict_types=1);

namespace Gcego\Layouts;

use Gcego\Layouts\Diagnostics\LayoutInspector;
use Gcego\Layouts\Diagnostics\LayoutReport;
use Gcego\Layouts\Registry\LayoutKind;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Rendering\Context\RenderMode;
use Gcego\Rendering\Facades\Rendering;
use Gcego\Security\SecurityManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Entry point for registered layouts:
 *
 *     app(LayoutManager::class)->render('dashboard');                 // resolve, authorize, render (Livewire children mount their hosts)
 *     app(LayoutManager::class)->render('layout:student', $student->id);   // the record goes to children (infolists, widgets)
 *     <x-gcego-layouts::layout name="dashboard" />
 *
 * Only registered identities are rendered; unknown ones are 404, and a
 * layout is denied unless its requirements allow (deny by default).
 */
final readonly class LayoutManager
{
    public function __construct(private Container $container, private LayoutsConfig $config) {}

    /**
     * The canonical identity ("layout:dashboard") of a layout name or identity.
     */
    public function identity(string $identity): string
    {
        $identity = str_contains($identity, ':') ? $identity : LayoutKind::Id.':'.$identity;

        if (preg_match('/^layout:[a-z][a-z0-9_-]{0,63}$/', $identity) !== 1) {
            throw new NotFoundHttpException('Layout not found.');
        }

        return $identity;
    }

    public function resolve(string $identity): Layout
    {
        $identity = $this->identity($identity);

        try {
            $layout = $this->container->make(RegistryInterface::class)->get($identity);
        } catch (Throwable) {
            throw new NotFoundHttpException('Layout not found.');
        }

        return $layout instanceof Layout ? $layout : throw new NotFoundHttpException('Layout not found.');
    }

    /**
     * gcego/security requirements from the layout down to $path. Throws AuthorizationException.
     */
    public function authorize(Layout $layout, ?string $path = null): void
    {
        $this->container->make(SecurityManager::class)->definition($layout, $path)->authorize();
    }

    public function allows(Layout $layout, ?string $path = null): bool
    {
        return $this->container->make(SecurityManager::class)->definition($layout, $path)->allowed;
    }

    /**
     * Resolve, authorize and render a registered layout. With $live (the
     * default for the Blade component), interactive children (widgets with
     * date ranges or refresh, infolists with actions) mount their Livewire
     * hosts; otherwise they render statically.
     */
    public function render(string $identity, string|int|null $record = null, bool $live = false): HtmlString
    {
        $layout = $this->resolve($identity);
        $this->authorize($layout);

        return $this->renderLayout($layout, $this->identity($identity), $record, $live);
    }

    /**
     * Render a layout definition (trusted server code: no authorization of
     * the root). Children authorize themselves; without an identity,
     * children that need a registered owner (widgets, tables) are refused.
     */
    public function renderLayout(LayoutComponent $layout, ?string $identity = null, string|int|null $record = null, bool $live = false): HtmlString
    {
        return new HtmlString(Rendering::render(
            $layout,
            $live ? RenderMode::Livewire : RenderMode::Static,
            identity: $identity,
            metadata: $record === null ? [] : ['record' => $record],
        )->toHtml());
    }

    public function inspect(string|LayoutComponent $layout): LayoutReport
    {
        return $this->container->make(LayoutInspector::class)->inspect($layout);
    }

    public function config(): LayoutsConfig
    {
        return $this->config;
    }
}
