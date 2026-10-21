<?php

declare(strict_types=1);

namespace Gcego\Components\Facades;

use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\Catalog\ComponentCatalog;
use Gcego\Components\Component;
use Gcego\Components\ComponentManager;
use Gcego\Components\Configuration\ComponentConfigurator;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Evaluation\Evaluator;
use Gcego\Components\Exceptions\InvalidConfigurationException;
use Gcego\Components\Inspection\ComponentInspection;
use Gcego\Components\Layout\Schema;
use Gcego\Components\Trees\ComponentTree;
use Gcego\Components\Validation\ValidationResult;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ComponentCatalog catalog()
 * @method static ComponentBuilder component(string $alias, string $name)
 * @method static ComponentBuilder define(class-string<Component> $component, array<int|string, mixed> $items = [])
 * @method static Component make(class-string<Component> $component = 'Gcego\Components\Component', array<int|string, mixed> $items = [])
 * @method static Schema schema(iterable<ComponentInterface|\Gcego\Components\Contracts\Buildable|iterable<mixed>|null> $components)
 * @method static \Gcego\Components\Collections\ComponentCollection collect(iterable<ComponentInterface|\Gcego\Components\Contracts\Buildable|iterable<mixed>|null> $components)
 * @method static ComponentTree tree(ComponentInterface $root)
 * @method static ValidationResult validate(ComponentInterface $root)
 * @method static array<string, mixed> normalize(ComponentInterface $root)
 * @method static mixed normalizeValue(mixed $value, string $location = 'value')
 * @method static ComponentInspection inspect(ComponentInterface $root)
 * @method static ComponentConfigurator configurator()
 * @method static Evaluator evaluator()
 * @method static \Gcego\Components\Traversal\ComponentWalker walker()
 * @method static \Gcego\Components\Patching\SchemaPatch patch(ComponentInterface|\Gcego\Components\Contracts\Buildable $schema)
 * @method static void extend(string $schema, \Closure $extension)
 * @method static ComponentInterface extended(string $schema, ComponentInterface|\Gcego\Components\Contracts\Buildable $component)
 * @method static \Gcego\Components\Live\LiveSchema live(ComponentInterface|\Gcego\Components\Contracts\Buildable $schema, \Gcego\Components\Evaluation\State|array<array-key, mixed> $state = [], \Gcego\Components\Evaluation\EvaluationContext|array<string, mixed> $context = [])
 *
 * @see ComponentManager
 */
final class Components extends Facade
{
    /**
     * The container owns the scoped lifetime; avoid retaining another app's manager.
     *
     * @var bool
     */
    protected static $cached = false;

    public static function getFacadeRoot(): ComponentManager
    {
        $manager = parent::getFacadeRoot();

        if (! $manager instanceof ComponentManager) {
            throw InvalidConfigurationException::invalidDefinition('The Components facade must resolve a ComponentManager instance.');
        }

        return $manager;
    }

    protected static function getFacadeAccessor(): string
    {
        if (self::$app === null || ! self::$app->bound(ComponentManager::class)) {
            throw InvalidConfigurationException::providerNotRegistered();
        }

        return ComponentManager::class;
    }
}
