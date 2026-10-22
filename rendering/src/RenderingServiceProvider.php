<?php

declare(strict_types=1);

namespace Gcego\Rendering;

use Gcego\Components\Component;
use Gcego\Components\ComponentServiceProvider;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Rendering\Contracts\RenderAuthorizer;
use Gcego\Rendering\Contracts\RenderHandler;
use Gcego\Rendering\Contracts\StylingResolver;
use Gcego\Rendering\Execution\RenderOperationHandler;
use Gcego\Rendering\Renderers\ComponentRenderer;
use Gcego\Rendering\Renderers\ContainerRenderer;
use Gcego\Rendering\Renderers\RendererRegistry;
use Gcego\Rendering\Security\GateRenderAuthorizer;
use Gcego\Rendering\State\StateCoordinator;
use Gcego\Rendering\Styling\NullStylingResolver;
use Gcego\Runtime\Execution\HandlerRegistry;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel integration for gcego/rendering; also the "gcego/rendering" GCEGO package.
 *
 * register() declares bindings, the built-in renderer, and the "render"
 * operation category in gcego/runtime's handler registry (when that registry
 * is first resolved); boot() registers the view namespace and publishing.
 * Nothing is rendered, discovered or loaded here.
 */
final class RenderingServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string Views = 'gcego-rendering';

    public function register(): void
    {
        $this->app->register(ComponentServiceProvider::class);

        $this->mergeConfigFrom(self::path('config/gcego-rendering.php'), 'gcego-rendering');

        $this->app->singleton(RenderingConfig::class, static function (Application $app): RenderingConfig {
            $config = $app->make(Repository::class);
            $rendering = $config->get('gcego-rendering', []);

            return RenderingConfig::fromArray(is_array($rendering) ? $rendering : [], (bool) $config->get('app.debug', false));
        });

        $this->app->singleton(RendererRegistry::class, static function (Application $app): RendererRegistry {
            $registry = new RendererRegistry($app);
            $registry->register(Component::class, ContainerRenderer::class, inherit: false);

            return $registry;
        });

        $this->app->singleton(StylingResolver::class, NullStylingResolver::class);
        $this->app->singleton(RenderAuthorizer::class, static fn (Application $app): RenderAuthorizer => new GateRenderAuthorizer(
            $app->make(Gate::class),
            $app->make(RenderingConfig::class)->ability,
        ));
        $this->app->singleton(ComponentRenderer::class);
        $this->app->singleton(StateCoordinator::class);
        $this->app->singleton(RenderEngine::class);

        $this->callAfterResolving(HandlerRegistry::class, static function (HandlerRegistry $handlers): void {
            $handlers->category(RenderEngine::OperationType, RenderHandler::class);
            $handlers->register(RenderEngine::OperationType, RenderOperationHandler::class);
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(self::path('resources/views'), self::Views);

        if ($this->app->runningInConsole()) {
            $this->publishes([self::path('config/gcego-rendering.php') => $this->app->configPath('gcego-rendering.php')], 'gcego-rendering-config');
            $this->publishes([self::path('resources/views') => $this->app->resourcePath('views/vendor/'.self::Views)], 'gcego-rendering-views');
        }
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/rendering',
            requires: ['gcego/core', 'gcego/registry', 'gcego/blueprints', 'gcego/runtime'],
            capabilities: ['rendering'],
            description: 'Renderer registry and Blade rendering of components and registered definitions.',
        );
    }

    private static function path(string $relative): string
    {
        return dirname(__DIR__).'/'.$relative;
    }
}
