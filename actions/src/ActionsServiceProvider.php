<?php

declare(strict_types=1);

namespace Gcego\Actions;

use Gcego\Actions\Authorization\SecurityActionAuthorizer;
use Gcego\Actions\Bulk\SelectionResolver;
use Gcego\Actions\Confirmation\ConfirmationManager;
use Gcego\Actions\Contracts\ActionAuthorizer;
use Gcego\Actions\Diagnostics\ActionInspector;
use Gcego\Actions\Execution\ActionExecutionEngine;
use Gcego\Actions\Execution\IdempotencyGuard;
use Gcego\Actions\Execution\TransactionCoordinator;
use Gcego\Actions\Input\InputResolvers;
use Gcego\Actions\Livewire\ActionHost;
use Gcego\Actions\Rendering\ActionRenderer;
use Gcego\Actions\Resolution\ActionResolver;
use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\Slots\SlotDefinition;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Runtime\Rendering\RendererRegistry;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Laravel integration for gcego/actions; also the "gcego/actions" GCEGO package.
 *
 * Registrations (configuration, placements, input resolvers, the resolver)
 * are singletons; the execution engine and authorizer are scoped, because
 * they depend on gcego/security's request-scoped services. Booting registers
 * renderers, the Livewire host, views, translations and styling slots; it
 * never queries, authorizes or loads definitions.
 */
final class ActionsServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string Views = 'gcego-actions';

    public const string LivewireAlias = 'gcego-actions';

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/gcego-actions.php', 'gcego-actions');

        $this->app->singleton(ActionsConfig::class, static function (Application $app): ActionsConfig {
            $config = $app->make(Repository::class)->get('gcego-actions', []);

            return ActionsConfig::fromArray(is_array($config) ? $config : []);
        });
        $this->app->singleton(ActionPlacement::class);
        $this->app->singleton(InputResolvers::class);
        $this->app->singleton(ActionResolver::class);
        $this->app->singleton(TransactionCoordinator::class);
        $this->app->singleton(ConfirmationManager::class, static fn (Application $app): ConfirmationManager => new ConfirmationManager(
            self::cache($app),
            $app->make(ActionsConfig::class),
            hash_hmac('sha256', 'gcego-actions.confirmation', self::appKey($app)),
        ));
        $this->app->singleton(IdempotencyGuard::class, static fn (Application $app): IdempotencyGuard => new IdempotencyGuard(self::cache($app), $app->make(ActionsConfig::class)));
        $this->app->singleton(SelectionResolver::class);
        $this->app->scoped(ActionAuthorizer::class, SecurityActionAuthorizer::class);
        $this->app->scoped(ActionExecutionEngine::class);
        $this->app->scoped(ActionInspector::class);
        $this->app->singleton(ActionManager::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(dirname(__DIR__).'/resources/views', self::Views);
        $this->loadTranslationsFrom(dirname(__DIR__).'/resources/lang', self::Views);
        $this->publishes([dirname(__DIR__).'/config/gcego-actions.php' => $this->app->configPath('gcego-actions.php')], 'gcego-actions-config');
        $this->publishes([dirname(__DIR__).'/resources/views' => $this->app->resourcePath('views/vendor/'.self::Views)], 'gcego-actions-views');

        // Validate the configuration at boot (pure config).
        $this->app->make(ActionsConfig::class);

        Livewire::component(self::LivewireAlias, ActionHost::class);

        $renderers = $this->app->make(RendererRegistry::class);
        $renderers->register(Action::class, ActionRenderer::class);
        $renderers->register(ActionGroup::class, ActionRenderer::class);

        $this->registerSlots($this->app->make(ChromaManager::class));
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/actions',
            requires: ['gcego/core', 'gcego/registry', 'gcego/runtime', 'gcego/chroma', 'gcego/data', 'gcego/security'],
            capabilities: ['actions', 'actions.crud', 'actions.bulk', 'actions.livewire'],
            description: 'Declarative CRUD, custom and bulk actions with secure execution, confirmation and transactions.',
        );
    }

    private static function appKey(Application $app): string
    {
        $key = $app->make(Repository::class)->get('app.key');

        return is_string($key) && $key !== '' ? $key : throw new \RuntimeException('gcego/actions signs confirmations with app.key; set APP_KEY.');
    }

    private static function cache(Application $app): \Illuminate\Contracts\Cache\Repository
    {
        return $app->make(CacheManager::class)->store($app->make(ActionsConfig::class)->cacheStore);
    }

    private function registerSlots(ChromaManager $styling): void
    {
        $slots = static fn (): array => [
            new SlotDefinition('root', 'control.button', description: 'The action button (layout, focus, motion, states).'),
            new SlotDefinition(ActionIntent::Primary->value, 'control.button.primary', description: 'Colors of primary actions.'),
            new SlotDefinition(ActionIntent::Secondary->value, 'control.button.secondary', description: 'Colors of secondary actions.'),
            new SlotDefinition(ActionIntent::Destructive->value, 'control.button.destructive', description: 'Colors of destructive actions.'),
        ];

        $styling->defineSlots(Action::class, $slots());
        $styling->defineSlots(ActionGroup::class, [
            new SlotDefinition('root', 'control.button'),
            new SlotDefinition('trigger', 'control.button.secondary'),
            new SlotDefinition('menu', 'surface.panel', ['flex', 'flex-col', 'gap-1', 'p-1']),
        ]);
    }
}
