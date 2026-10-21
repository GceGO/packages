<?php

declare(strict_types=1);

namespace Gcego\Components;

use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\Catalog\ComponentCatalog;
use Gcego\Components\Configuration\ComponentConfigurator;
use Gcego\Components\Evaluation\Evaluator;
use Gcego\Components\Inspection\ComponentInspector;
use Gcego\Components\Normalization\ComponentNormalizer;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Components\Trees\ComponentTree;
use Gcego\Components\Validation\ChildValidator;
use Gcego\Components\Validation\ComponentValidator;
use Gcego\Components\Validation\PropertyValidator;
use Illuminate\Container\Container;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Required registration point for component services and factories.
 */
final class ComponentServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        foreach ($this->provides() as $service) {
            if ($this->hasBinding($service)) {
                continue;
            }

            if ($service === ComponentCatalog::class) {
                $this->app->scoped($service);
            } elseif ($service === ComponentManager::class) {
                $this->app->scoped($service, fn (): ComponentManager => new ComponentManager($this->app));
            } elseif ($service === ComponentBuilder::class || $service === ComponentTree::class) {
                $this->app->bind($service);
            } else {
                $this->app->singleton($service);
            }
        }
    }

    private function hasBinding(string $service): bool
    {
        if (! $this->app instanceof Container) {
            return $this->app->bound($service);
        }

        return isset($this->app->getBindings()[$service])
            || $this->app->isShared($service)
            || $this->app->isAlias($service);
    }

    /**
     * @return list<class-string>
     */
    public function provides(): array
    {
        return [
            ComponentManager::class,
            ComponentWalker::class,
            PropertyValidator::class,
            ChildValidator::class,
            ComponentValidator::class,
            ComponentNormalizer::class,
            ComponentInspector::class,
            ComponentConfigurator::class,
            Evaluator::class,
            ComponentBuilder::class,
            ComponentTree::class,
            ComponentCatalog::class,
        ];
    }
}
