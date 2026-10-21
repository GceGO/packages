<?php

declare(strict_types=1);

namespace Gcego\Components;

use Closure;
use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\Catalog\ComponentCatalog;
use Gcego\Components\Collections\ComponentCollection;
use Gcego\Components\Configuration\ComponentConfigurator;
use Gcego\Components\Contracts\Buildable;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Evaluation\EvaluationContext;
use Gcego\Components\Evaluation\Evaluator;
use Gcego\Components\Evaluation\State;
use Gcego\Components\Exceptions\InvalidBuilderException;
use Gcego\Components\Exceptions\InvalidConfigurationException;
use Gcego\Components\Facades\Components;
use Gcego\Components\Inspection\ComponentInspection;
use Gcego\Components\Inspection\ComponentInspector;
use Gcego\Components\Layout\Schema;
use Gcego\Components\Live\LiveSchema;
use Gcego\Components\Normalization\ComponentNormalizer;
use Gcego\Components\Patching\SchemaPatch;
use Gcego\Components\Properties\Property;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Components\Trees\ComponentTree;
use Gcego\Components\Validation\ComponentValidator;
use Gcego\Components\Validation\ValidationResult;
use Illuminate\Contracts\Container\Container as ContainerContract;
use ReflectionClass;

/**
 * The provider-registered entry point for component construction and services.
 */
final class ComponentManager
{
    /**
     * @var array<class-string<Component>, ComponentDefinition>
     */
    private array $definitions = [];

    public function __construct(private readonly ContainerContract $container) {}

    /**
     * @internal Schema factories are supplied by Component::definition().
     *
     * @param  class-string<Component>  $component
     * @param  Closure(): ComponentDefinition  $factory
     */
    public function rememberDefinition(string $component, Closure $factory): ComponentDefinition
    {
        $this->assertActive();

        return $this->definitions[$component] ??= $factory();
    }

    /**
     * @param  class-string<Component>  $component
     * @param  array<int|string, mixed>  $items
     */
    public function define(string $component, array $items = []): ComponentBuilder
    {
        $this->assertActive();

        if (! is_a($component, Component::class, true)) {
            throw InvalidBuilderException::notAComponent($component);
        }

        if (! (new ReflectionClass($component))->isInstantiable()) {
            throw InvalidBuilderException::notInstantiableComponent($component);
        }

        $builderClass = $component::builderClass();

        if (($builderClass !== ComponentBuilder::class && ! is_subclass_of($builderClass, ComponentBuilder::class, true))
            || ! (new ReflectionClass($builderClass))->isInstantiable()) {
            throw InvalidBuilderException::invalidBuilder($component, $builderClass);
        }

        $builder = $this->container->make($builderClass, ['component' => $component, 'items' => $items]);

        if (! $builder instanceof ComponentBuilder || $builder->componentClass() !== $component) {
            throw InvalidBuilderException::invalidBuilder($component, $builderClass);
        }

        return $builder;
    }

    /**
     * The application's configureUsing() modifiers and builder macros.
     */
    public function configurator(): ComponentConfigurator
    {
        $this->assertActive();

        return $this->container->make(ComponentConfigurator::class);
    }

    /**
     * Calls closures with Filament-style parameter injection.
     */
    public function evaluator(): Evaluator
    {
        $this->assertActive();

        return $this->container->make(Evaluator::class);
    }

    /**
     * Bring a schema to life over state: defaults are filled, closures
     * evaluated, dependencies discovered and updates diffed.
     *
     * @param  State|array<array-key, mixed>  $state
     * @param  EvaluationContext|array<string, mixed>  $context
     */
    public function live(ComponentInterface|Buildable $schema, State|array $state = [], EvaluationContext|array $context = []): LiveSchema
    {
        $this->assertActive();

        return new LiveSchema(
            $schema instanceof Buildable ? $schema->build() : $schema,
            $state instanceof State ? $state : new State($state),
            EvaluationContext::from($context),
            $this->walker(),
            $this->evaluator(),
        );
    }

    public function walker(): ComponentWalker
    {
        $this->assertActive();

        return $this->container->make(ComponentWalker::class);
    }

    /**
     * Start immutable edits to a schema: modify, remove, insert, append.
     */
    public function patch(ComponentInterface|Buildable $schema): SchemaPatch
    {
        $this->assertActive();

        return new SchemaPatch($schema instanceof Buildable ? $schema->build() : $schema);
    }

    /**
     * Let any package extend the schema published under a key, typically
     * from a service provider's boot():
     *
     *     Components::extend('customers.form', fn (SchemaPatch $patch) => $patch
     *         ->insertAfter('email', TextInput::make('loyalty_number')));
     *
     * @param  Closure(SchemaPatch): SchemaPatch  $extension
     */
    public function extend(string $schema, Closure $extension): void
    {
        $this->configurator()->extend($schema, $extension);
    }

    /**
     * Publish a schema under a key, applying every registered extension in
     * registration order (later extensions see earlier additions).
     */
    public function extended(string $schema, ComponentInterface|Buildable $component): ComponentInterface
    {
        $component = $component instanceof Buildable ? $component->build() : $component;

        foreach ($this->configurator()->extensionsFor($schema) as $extension) {
            $patch = $extension($this->patch($component));

            if (! $patch instanceof SchemaPatch) {
                throw InvalidBuilderException::invalidValue(Component::class, 'extend', sprintf('Extensions of "%s" must return the patch: fn (SchemaPatch $patch) => $patch->remove(\'field\').', $schema));
            }

            $component = $patch->apply();
        }

        return $component;
    }

    public function catalog(): ComponentCatalog
    {
        $this->assertActive();

        return $this->container->make(ComponentCatalog::class);
    }

    public function component(string $alias, string $name): ComponentBuilder
    {
        $this->assertActive();
        $class = $this->catalog()->resolve($alias);

        return $this->define($class, [new Property(Component::Name, $name)]);
    }

    /**
     * @param  iterable<ComponentInterface|Buildable|iterable<mixed>|null>  $components
     */
    public function schema(iterable $components): Schema
    {
        $schema = $this->define(Schema::class)->components($components)->build();

        if (! $schema instanceof Schema) {
            throw InvalidBuilderException::wrongComponent(Schema::class, $schema::class);
        }

        return $schema;
    }

    /**
     * @param  iterable<ComponentInterface|Buildable|iterable<mixed>|null>  $components
     */
    public function collect(iterable $components): ComponentCollection
    {
        return $this->schema($components)->children();
    }

    /**
     * @param  class-string<Component>  $component
     * @param  array<int|string, mixed>  $items
     */
    public function make(string $component = Component::class, array $items = []): Component
    {
        $built = $this->define($component, $items)->build();

        if (! $built instanceof $component) {
            throw InvalidBuilderException::wrongComponent($component, $built::class);
        }

        return $built;
    }

    public function tree(ComponentInterface $root): ComponentTree
    {
        $this->assertActive();

        return $this->container->make(ComponentTree::class, ['root' => $root]);
    }

    public function validate(ComponentInterface $root): ValidationResult
    {
        $this->assertActive();

        return $this->container->make(ComponentValidator::class)->validate($root);
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(ComponentInterface $root): array
    {
        $this->assertActive();

        return $this->container->make(ComponentNormalizer::class)->normalize($root);
    }

    public function normalizeValue(mixed $value, string $location = 'value'): mixed
    {
        $this->assertActive();

        return $this->container->make(ComponentNormalizer::class)->normalizeValue($value, $location);
    }

    public function inspect(ComponentInterface $root): ComponentInspection
    {
        $this->assertActive();

        return $this->container->make(ComponentInspector::class)->inspect($root);
    }

    /**
     * @return array{container: string, definitionCount: int}
     */
    public function __debugInfo(): array
    {
        return ['container' => '[redacted]', 'definitionCount' => count($this->definitions)];
    }

    /**
     * @return array<never, never>
     */
    public function __serialize(): array
    {
        throw InvalidConfigurationException::managerNotSerializable();
    }

    private function assertActive(): void
    {
        if (Components::getFacadeRoot() !== $this) {
            throw InvalidConfigurationException::inactiveManager();
        }
    }
}
