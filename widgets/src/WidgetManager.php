<?php

declare(strict_types=1);

namespace Gcego\Widgets;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Data\DataManager;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Rendering\Context\RenderMode;
use Gcego\Rendering\Facades\Rendering;
use Gcego\Security\SecurityManager;
use Gcego\Widgets\Data\WidgetDataContext;
use Gcego\Widgets\Data\WidgetDataResolver;
use Gcego\Widgets\Data\WidgetDataResult;
use Gcego\Widgets\Diagnostics\WidgetInspector;
use Gcego\Widgets\Diagnostics\WidgetReport;
use Gcego\Widgets\Registry\WidgetKind;
use Gcego\Widgets\Rendering\WidgetRenderContext;
use Gcego\Widgets\Rendering\WidgetRenderFrame;
use Gcego\Widgets\State\WidgetState;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * The entry point of gcego/widgets (singleton; per-user services resolved per call):
 *
 *     $widgets = app(WidgetManager::class);
 *     echo $widgets->render('total_students');                      // authorize, resolve data, render (Blade, no Livewire)
 *     echo $widgets->render('enrolment', ['range' => 'this_month']);
 *
 *     $widget = $widgets->resolve('widget:total_students');         // registered widgets only
 *     $widgets->authorize($widget);                                 // gcego/security requirements (403)
 *     $result = $widgets->data($widget, $widgets->context('widget:total_students', $widget));
 *
 * Widgets are looked up by registry identity only; a request can never
 * name a widget class, provider or model.
 */
final readonly class WidgetManager
{
    /** A registered owner identity of another kind ("layout:dashboard", "blueprint:admin/students"). */
    private const string OwnerPattern = '/^[a-z][a-z0-9_-]*:[A-Za-z0-9_.\/-]+$/';

    public function __construct(private Container $container, private WidgetsConfig $config) {}

    /**
     * The canonical identity ("widget:total_students") of a registered widget.
     */
    public function identity(string $identity): string
    {
        $identity = str_contains($identity, ':') ? $identity : WidgetKind::Id.':'.$identity;

        if (preg_match('/^widget:[a-z][a-z0-9_]{0,63}$/', $identity) !== 1) {
            throw new NotFoundHttpException('Widget not found.');
        }

        return $identity;
    }

    public function resolve(string $identity): Widget
    {
        $identity = $this->identity($identity);

        try {
            $widget = $this->container->make(RegistryInterface::class)->get($identity);
        } catch (Throwable) {
            throw new NotFoundHttpException('Widget not found.');
        }

        return $widget instanceof Widget ? $widget : throw new NotFoundHttpException('Widget not found.');
    }

    /**
     * gcego/security requirements from the widget (or the owner of an embedded
     * widget) down to $path. Throws AuthorizationException.
     */
    public function authorize(ComponentInterface $widget, ?string $path = null): void
    {
        $this->container->make(SecurityManager::class)->definition($widget, $path)->authorize();
    }

    public function allows(ComponentInterface $widget, ?string $path = null): bool
    {
        return $this->container->make(SecurityManager::class)->definition($widget, $path)->allowed;
    }

    /**
     * The widget at a path of a registered owner ("layout:dashboard", "blueprint:students"),
     * with the owner its requirements are inherited from. Unknown owners and
     * paths without a widget are 404.
     *
     * @return array{0: ComponentInterface, 1: Widget}
     */
    public function locate(string $owner, string $path): array
    {
        if (preg_match(self::OwnerPattern, $owner) !== 1 || str_starts_with($owner, WidgetKind::Id.':')) {
            throw new NotFoundHttpException('Widget not found.');
        }

        try {
            $definition = $this->container->make(RegistryInterface::class)->get($owner);
        } catch (Throwable) {
            throw new NotFoundHttpException('Widget not found.');
        }

        if (! $definition instanceof ComponentInterface) {
            throw new NotFoundHttpException('Widget not found.');
        }

        foreach (app(ComponentWalker::class)->preOrder($definition) as $node) {
            if ($node->path->toString() === $path) {
                return $node->component instanceof Widget ? [$definition, $node->component] : throw new NotFoundHttpException('Widget not found.');
            }
        }

        throw new NotFoundHttpException('Widget not found.');
    }

    /**
     * @param  array<array-key, mixed>  $input  Untrusted state: range, from, to, page.
     */
    public function state(Widget $widget, array $input = []): WidgetState
    {
        return WidgetState::from($widget, $input, $this->config);
    }

    public function context(string $identity, Widget $widget, ?WidgetState $state = null, string|int|null $record = null, ?string $path = null): WidgetDataContext
    {
        $security = $this->container->make(SecurityManager::class)->context();
        $state ??= $this->state($widget);
        $zone = $this->config->timezone ?? (is_string($app = config('app.timezone')) ? $app : 'UTC');

        return new WidgetDataContext(
            widget: $identity,
            path: $path ?? $widget->widgetName(),
            user: $security->user,
            tenant: $security->tenant,
            period: $state->period,
            record: $record,
            page: $state->page,
            locale: app()->getLocale(),
            timezone: $zone,
            data: $this->container->make(DataManager::class),
        );
    }

    public function data(Widget $widget, WidgetDataContext $context, ?WidgetDataResult $shared = null): WidgetDataResult
    {
        return $this->container->make(WidgetDataResolver::class)->resolve($widget, $context, $shared);
    }

    /**
     * Resolve, authorize and render a widget as plain Blade (no Livewire).
     *
     * @param  array<array-key, mixed>  $state
     */
    public function render(string $identity, array $state = [], string|int|null $record = null): HtmlString
    {
        $widget = $this->resolve($identity);
        $this->authorize($widget);

        return $this->renderWidget($this->identity($identity), $widget, $this->state($widget, $state), $record);
    }

    /**
     * Render an already authorized widget (WidgetHost, render()). An embedded
     * widget passes its owner and its path in the owner.
     */
    public function renderWidget(string $identity, Widget $widget, WidgetState $state, string|int|null $record = null, ?string $hostId = null, bool $live = false, ?ComponentInterface $owner = null, ?string $path = null): HtmlString
    {
        $frame = new WidgetRenderFrame($identity, $owner ?? $widget, $state, $record, $hostId ?? 'gcego-widget-'.substr(hash('sha256', $identity.'|'.$path), 0, 8), $live, base: $owner === null ? null : $path);
        $html = $this->container->make(WidgetRenderContext::class)->run($frame, fn (): string => Rendering::render($widget, $live ? RenderMode::Livewire : RenderMode::Static, identity: $identity)->toHtml());

        return new HtmlString($html);
    }

    /**
     * Invalidate every cached result of a widget (call it when its data
     * changes). For embedded widgets, pass the owner ("layout:dashboard"):
     * every cached widget of that owner is invalidated.
     */
    public function forget(string $identity): void
    {
        $owned = preg_match(self::OwnerPattern, $identity) === 1 && ! str_starts_with($identity, WidgetKind::Id.':');
        $this->container->make(WidgetDataResolver::class)->forget($owned ? $identity : $this->identity($identity));
    }

    public function inspect(string|Widget $widget): WidgetReport
    {
        return $this->container->make(WidgetInspector::class)->inspect($widget);
    }

    public function config(): WidgetsConfig
    {
        return $this->config;
    }
}
