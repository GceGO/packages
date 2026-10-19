<?php

declare(strict_types=1);

namespace Gcego\Components;

use BackedEnum;
use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Components\Exceptions\DuplicatePropertyException;
use Gcego\Components\Exceptions\InvalidComponentException;
use Gcego\Components\Exceptions\MissingPropertyException;
use Gcego\Components\Normalization\ComponentNormalizer;
use Gcego\Components\Support\ConstructorArguments;
use Gcego\Components\Validation\ComponentValidator;
use Gcego\Components\Validation\ValidationResult;
use UnitEnum;

/**
 * The base of every GCEGO component.
 *
 * A component is declared fluently through its builder:
 *
 *     Text::define('name', 'Subject Name')->searchable()->sortable()
 *
 * or by passing items to its constructor:
 *
 *     new Component(
 *         new Property(Component::Name, 'dashboard'),
 *         new Component(new Property(Component::Name, 'statistics')),
 *     );
 *
 * Properties, child components and configuration nodes are told apart by their
 * contracts. Subclasses describe themselves by overriding defineSchema() and,
 * optionally, shorthand(). Instances are immutable; the with*() methods return
 * modified copies of the same concrete class.
 *
 * Construction enforces structural rules (supported items, unique property
 * keys, safe values). Schema rules (required, types, unknown properties,
 * children) are checked by validate() / assertValid() over the whole tree so
 * every error can be reported with its path.
 */
class Component implements ComponentInterface
{
    public const string Name = 'name';

    public const string Label = 'label';

    public const string Description = 'description';

    public const string Id = 'id';

    /**
     * Explicit ids must start with a letter and contain only letters, digits, "_", "-", "." or ":".
     */
    public const string IdPattern = '/^[A-Za-z][A-Za-z0-9_.:\-]*$/';

    /**
     * @var array<class-string, ComponentDefinition>
     */
    private static array $definitions = [];

    private PropertyCollection $properties;

    private ComponentCollection $children;

    private ConfigurationCollection $configurations;

    private ?ValidationResult $validationResult = null;

    /**
     * @param  Property|ComponentInterface|ConfigurationNode|scalar|UnitEnum|array<mixed>|null  ...$items
     *                                                                                                     Leading non-item values are mapped to the keys returned by shorthand().
     */
    public function __construct(mixed ...$items)
    {
        $parsed = ConstructorArguments::parse(static::class, static::shorthand(), $items);

        try {
            $this->properties = new PropertyCollection(...self::castBackedEnums($parsed->properties));
        } catch (DuplicatePropertyException $exception) {
            throw DuplicatePropertyException::forKey(self::duplicateKey($parsed->properties), static::class, $exception);
        }

        $this->children = new ComponentCollection(...$parsed->children);
        $this->configurations = new ConfigurationCollection(...$parsed->configurations);
    }

    /**
     * Start a fluent definition of this component.
     *
     * Leading positional values follow shorthand(); named values set the
     * property their name maps to (field: 'user_id' sets Field); children,
     * builders, properties and configuration nodes are accepted as in the
     * constructor. The builder is built when it is placed in a parent.
     */
    public static function define(mixed ...$items): ComponentBuilder
    {
        $builder = static::builderClass();

        return new $builder(static::class, $items);
    }

    /**
     * The builder define() returns. Override to give a component richer fluent methods.
     *
     * @return class-string<ComponentBuilder>
     */
    public static function builderClass(): string
    {
        return ComponentBuilder::class;
    }

    /**
     * The property keys positional define()/constructor values are assigned to.
     *
     * @return list<string>
     */
    final public static function shorthandKeys(): array
    {
        return static::shorthand();
    }

    /**
     * The schema of this class, built once and cached for the process lifetime.
     */
    final public static function definition(): ComponentDefinition
    {
        return self::$definitions[static::class] ??= static::defineSchema();
    }

    /**
     * Describe the schema of this component class.
     *
     * Override and extend the parent definition:
     *
     *     protected static function defineSchema(): ComponentDefinition
     *     {
     *         return parent::defineSchema()
     *             ->withType('tables.column.text')
     *             ->withProperties(new PropertyDefinition(self::Searchable, Type::bool(), default: false))
     *             ->withoutChildren();
     *     }
     *
     * The generic base Component accepts unknown properties; every subclass
     * rejects them unless it calls withUnknownProperties().
     */
    protected static function defineSchema(): ComponentDefinition
    {
        return new ComponentDefinition(
            type: str_contains(static::class, '@anonymous') ? 'anonymous' : static::class,
            properties: [
                new PropertyDefinition(
                    self::Name,
                    Type::string(),
                    rule: static fn (mixed $value): bool => $value !== '',
                    ruleDescription: 'non-empty string',
                ),
                new PropertyDefinition(self::Label, Type::string()),
                new PropertyDefinition(self::Description, Type::string()),
                new PropertyDefinition(
                    self::Id,
                    Type::string(),
                    rule: static fn (mixed $value): bool => is_string($value) && preg_match(self::IdPattern, $value) === 1,
                    ruleDescription: 'identifier matching '.self::IdPattern,
                ),
            ],
            allowsUnknownProperties: static::class === self::class,
        );
    }

    /**
     * Property keys that leading positional constructor values are assigned to.
     *
     * Returning [self::Name, self::Label] enables new Text('name', 'Subject Name').
     *
     * @return list<string>
     */
    protected static function shorthand(): array
    {
        return [];
    }

    final public function type(): string
    {
        return static::definition()->type;
    }

    final public function id(): ?string
    {
        $id = $this->properties->value(self::Id);

        return is_string($id) ? $id : null;
    }

    final public function name(): ?string
    {
        $name = $this->get(self::Name);

        return is_string($name) ? $name : null;
    }

    final public function properties(): PropertyCollection
    {
        return $this->properties;
    }

    final public function get(string $key, mixed $fallback = null): mixed
    {
        $property = $this->properties->get($key);

        if ($property !== null) {
            return $property->value;
        }

        $definition = static::definition()->property($key);

        return $definition !== null && $definition->hasDefault ? $definition->default : $fallback;
    }

    /**
     * Like get(), but fails loudly when there is neither a value nor a default.
     *
     * @throws MissingPropertyException
     */
    final public function require(string $key): mixed
    {
        if (! $this->has($key)) {
            throw MissingPropertyException::forKey($key, $this->type(), static::class);
        }

        return $this->get($key);
    }

    final public function has(string $key): bool
    {
        return $this->properties->has($key) || static::definition()->property($key)?->hasDefault === true;
    }

    final public function hasExplicit(string $key): bool
    {
        return $this->properties->has($key);
    }

    /**
     * @return array<string, mixed>
     */
    final public function defaults(): array
    {
        return static::definition()->defaults();
    }

    final public function effectiveProperties(): array
    {
        $effective = array_replace(static::definition()->defaults(), $this->properties->toArray());
        ksort($effective);

        return $effective;
    }

    final public function children(): ComponentCollection
    {
        return $this->children;
    }

    final public function childCount(): int
    {
        return $this->children->count();
    }

    final public function hasChildren(): bool
    {
        return ! $this->children->isEmpty();
    }

    final public function configurations(): ConfigurationCollection
    {
        return $this->configurations;
    }

    /**
     * Validate this component and all of its descendants. The result is memoized.
     */
    final public function validate(): ValidationResult
    {
        return $this->validationResult ??= (new ComponentValidator)->validate($this);
    }

    /**
     * @throws InvalidComponentException
     */
    final public function assertValid(): static
    {
        $this->validate()->throwIfInvalid();

        return $this;
    }

    final public function normalize(): array
    {
        return (new ComponentNormalizer)->normalize($this);
    }

    final public function tree(): ComponentTree
    {
        return new ComponentTree($this);
    }

    /**
     * Copy with the given properties added or replaced.
     */
    final public function withProperty(Property ...$properties): static
    {
        $copy = clone $this;
        $copy->properties = $this->properties->with(...self::castBackedEnums($properties));

        return $copy;
    }

    final public function withoutProperty(string ...$keys): static
    {
        $copy = clone $this;
        $copy->properties = $this->properties->without(...$keys);

        return $copy;
    }

    /**
     * Copy with the children replaced.
     */
    final public function withChildren(ComponentInterface ...$children): static
    {
        $copy = clone $this;
        $copy->children = new ComponentCollection(...$children);

        return $copy;
    }

    /**
     * Copy with children appended after the existing ones.
     */
    final public function withAppendedChildren(ComponentInterface ...$children): static
    {
        $copy = clone $this;
        $copy->children = new ComponentCollection(...$this->children->all(), ...$children);

        return $copy;
    }

    /**
     * Copy with the configuration nodes replaced.
     */
    final public function withConfigurations(ConfigurationNode ...$configurations): static
    {
        $copy = clone $this;
        $copy->configurations = new ConfigurationCollection(...$configurations);

        return $copy;
    }

    final public function __clone()
    {
        $this->validationResult = null;
    }

    /**
     * A string-backed enum case given to a property that takes strings but not
     * that enum is stored as its value: new Property(Resource::Icon, Heroicon::Home)
     * holds "heroicon:home". Properties typed for the enum keep the case.
     *
     * @param  array<array-key, Property>  $properties
     * @return list<Property>
     */
    private static function castBackedEnums(array $properties): array
    {
        $definition = static::definition();
        $cast = [];

        foreach ($properties as $property) {
            $value = $property->value;
            $types = $definition->property($property->key)->types ?? [];

            if ($value instanceof BackedEnum && is_string($value->value)
                && array_filter($types, static fn (Type $type): bool => $type->accepts($value)) === []
                && array_filter($types, static fn (Type $type): bool => $type->kind === TypeKind::String) !== []) {
                $property = new Property($property->key, $value->value);
            }

            $cast[] = $property;
        }

        return $cast;
    }

    /**
     * @param  list<Property>  $properties
     */
    private static function duplicateKey(array $properties): string
    {
        $seen = [];

        foreach ($properties as $property) {
            if (isset($seen[$property->key])) {
                return $property->key;
            }

            $seen[$property->key] = true;
        }

        return '';
    }
}
