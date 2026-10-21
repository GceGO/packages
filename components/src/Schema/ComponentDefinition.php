<?php

declare(strict_types=1);

namespace Gcego\Components\Schema;

use Closure;
use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Contracts\Concern;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Components\Contracts\PlacementConfiguration;
use Gcego\Components\Exceptions\InvalidConfigurationException;
use Gcego\Components\Support\ValueDescriber;

/**
 * The immutable schema of a component class.
 *
 * Definitions are built once per class in the active Laravel scope and are
 * shared within that scope. Modifier methods return new definitions so a
 * subclass can extend its parent's schema without affecting it.
 */
final readonly class ComponentDefinition
{
    public const string TypePattern = '/\A[A-Za-z_][A-Za-z0-9_.:\\\\\-]*\z/';

    /**
     * @var array<string, PropertyDefinition>
     */
    private array $properties;

    /**
     * @var array<string, mixed>
     */
    private array $defaults;

    /**
     * @var list<string>
     */
    private array $requiredProperties;

    /**
     * @var list<class-string<ComponentInterface>>|null
     */
    public ?array $allowedChildren;

    /**
     * @var list<class-string<ConfigurationNode>>|null
     */
    public ?array $allowedConfigurations;

    /**
     * @var list<Closure(ComponentInterface): (iterable<string>|string|null)>
     */
    private array $constraints;

    /**
     * @param  list<PropertyDefinition>  $properties
     * @param  list<class-string<ComponentInterface>>|null  $allowedChildren  Null allows any component.
     * @param  list<class-string<ConfigurationNode>>|null  $allowedConfigurations  Null allows any configuration node.
     * @param  list<Closure(ComponentInterface): (iterable<string>|string|null)>  $constraints  Each returns error message(s), or null when satisfied.
     */
    public function __construct(
        public string $type,
        array $properties = [],
        public bool $allowsUnknownProperties = false,
        public bool $allowsChildren = true,
        ?array $allowedChildren = null,
        public int $minChildren = 0,
        public ?int $maxChildren = null,
        ?array $allowedConfigurations = null,
        array $constraints = [],
    ) {
        if (preg_match(self::TypePattern, $type) !== 1) {
            throw InvalidConfigurationException::invalidDefinition(sprintf('"%s" is not a valid component type identifier.', $type));
        }

        $indexed = [];
        $defaults = [];
        $required = [];

        foreach ($properties as $property) {
            if (! $property instanceof PropertyDefinition) {
                throw InvalidConfigurationException::invalidDefinition(sprintf('Properties of "%s" must be %s instances.', $type, PropertyDefinition::class));
            }

            if (isset($indexed[$property->name])) {
                throw InvalidConfigurationException::invalidDefinition(sprintf('Property "%s" is defined more than once on "%s".', $property->name, $type));
            }

            $indexed[$property->name] = $property;

            if ($property->hasDefault) {
                $defaults[$property->name] = $property->default;
            }

            if ($property->required) {
                $required[] = $property->name;
            }
        }

        if ($minChildren < 0 || ($maxChildren !== null && $maxChildren < $minChildren)) {
            throw InvalidConfigurationException::invalidDefinition(sprintf('Child count bounds of "%s" are invalid (min %d, max %s).', $type, $minChildren, $maxChildren ?? 'none'));
        }

        if (! $allowsChildren && $minChildren > 0) {
            throw InvalidConfigurationException::invalidDefinition(sprintf('"%s" cannot require children while disallowing them.', $type));
        }

        $constraintSnapshot = [];

        foreach ($constraints as $constraint) {
            if (! $constraint instanceof Closure) {
                throw InvalidConfigurationException::invalidDefinition(sprintf('Constraints of "%s" must be closures.', $type));
            }

            $constraintSnapshot[] = $constraint;
        }

        ksort($defaults);

        $this->properties = $indexed;
        $this->defaults = $defaults;
        $this->requiredProperties = $required;
        $this->allowedChildren = $allowedChildren === null ? null : self::assertClasses($type, $allowedChildren, ComponentInterface::class);
        $this->allowedConfigurations = $allowedConfigurations === null ? null : self::assertClasses($type, $allowedConfigurations, ConfigurationNode::class);
        $this->constraints = $constraintSnapshot;
    }

    public function hasProperty(string $name): bool
    {
        return isset($this->properties[$name]);
    }

    public function property(string $name): ?PropertyDefinition
    {
        return $this->properties[$name] ?? null;
    }

    /**
     * @return array<string, PropertyDefinition>
     */
    public function properties(): array
    {
        return $this->properties;
    }

    /**
     * @return list<string>
     */
    public function propertyNames(): array
    {
        return array_keys($this->properties);
    }

    /**
     * @return list<string>
     */
    public function requiredProperties(): array
    {
        return $this->requiredProperties;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return $this->defaults;
    }

    /**
     * @return list<Closure(ComponentInterface): (iterable<string>|string|null)>
     */
    public function constraints(): array
    {
        return $this->constraints;
    }

    public function allowsChild(ComponentInterface $child): bool
    {
        return $this->allowsChildren && self::matchesAny($child, $this->allowedChildren);
    }

    public function allowsConfiguration(ConfigurationNode $configuration): bool
    {
        return $configuration instanceof PlacementConfiguration || self::matchesAny($configuration, $this->allowedConfigurations);
    }

    public function withType(string $type): self
    {
        return $this->copy(['type' => $type]);
    }

    /**
     * Add properties, replacing existing definitions with the same name.
     */
    public function withProperties(PropertyDefinition ...$properties): self
    {
        $merged = $this->properties;

        foreach ($properties as $property) {
            $merged[$property->name] = $property;
        }

        return $this->copy(['properties' => array_values($merged)]);
    }

    /**
     * Add the property definitions of reusable concerns, later ones replacing earlier ones.
     *
     * @param  class-string<Concern>  ...$concerns
     */
    public function withConcerns(string ...$concerns): self
    {
        $properties = [];

        foreach ($concerns as $concern) {
            if (! is_a($concern, Concern::class, true)) {
                throw InvalidConfigurationException::invalidDefinition(sprintf('"%s" is not a %s.', $concern, Concern::class));
            }

            array_push($properties, ...$concern::properties());
        }

        return $this->withProperties(...$properties);
    }

    /** @param iterable<Field|PropertyDefinition> $fields */
    public function withFields(iterable $fields): self
    {
        $properties = [];

        foreach ($fields as $field) {
            if (! $field instanceof Field && ! $field instanceof PropertyDefinition) {
                throw InvalidConfigurationException::invalidDefinition('Schema fields must be Field builders or PropertyDefinition instances.');
            }

            $properties[] = $field instanceof Field ? $field->build() : $field;
        }

        return $this->withProperties(...$properties);
    }

    public function withoutProperties(string ...$names): self
    {
        return $this->copy(['properties' => array_values(array_diff_key($this->properties, array_flip($names)))]);
    }

    public function withUnknownProperties(bool $allowed = true): self
    {
        return $this->copy(['allowsUnknownProperties' => $allowed]);
    }

    /**
     * @param  list<class-string<ComponentInterface>>|null  $types  Null allows any component.
     */
    public function withAllowedChildren(?array $types, int $min = 0, ?int $max = null): self
    {
        return $this->copy(['allowsChildren' => true, 'allowedChildren' => $types, 'minChildren' => $min, 'maxChildren' => $max]);
    }

    public function withoutChildren(): self
    {
        return $this->copy(['allowsChildren' => false, 'allowedChildren' => [], 'minChildren' => 0, 'maxChildren' => null]);
    }

    /**
     * @param  list<class-string<ConfigurationNode>>|null  $types  Null allows any configuration node.
     */
    public function withAllowedConfigurations(?array $types): self
    {
        return $this->copy(['allowedConfigurations' => $types]);
    }

    /**
     * @param  Closure(ComponentInterface): (iterable<string>|string|null)  $constraint
     */
    public function withConstraint(Closure $constraint): self
    {
        return $this->copy(['constraints' => [...$this->constraints, $constraint]]);
    }

    /**
     * Schema metadata for tooling and normalization. Closures are reported as counts only.
     *
     * @return array{type: string, properties: array<string, array<string, mixed>>, allowsUnknownProperties: bool, allowsChildren: bool, allowedChildren: list<string>|null, minChildren: int, maxChildren: int|null, allowedConfigurations: list<string>|null, constraintCount: int}
     */
    public function toArray(): array
    {
        $properties = array_map(static fn (PropertyDefinition $property): array => $property->toArray(), $this->properties);
        ksort($properties);

        return [
            'type' => $this->type,
            'properties' => $properties,
            'allowsUnknownProperties' => $this->allowsUnknownProperties,
            'allowsChildren' => $this->allowsChildren,
            'allowedChildren' => $this->allowedChildren === null ? null : array_map(ValueDescriber::className(...), $this->allowedChildren),
            'minChildren' => $this->minChildren,
            'maxChildren' => $this->maxChildren,
            'allowedConfigurations' => $this->allowedConfigurations === null ? null : array_map(ValueDescriber::className(...), $this->allowedConfigurations),
            'constraintCount' => count($this->constraints),
        ];
    }

    /**
     * @param  array{type?: string, properties?: list<PropertyDefinition>, allowsUnknownProperties?: bool, allowsChildren?: bool, allowedChildren?: list<class-string<ComponentInterface>>|null, minChildren?: int, maxChildren?: int|null, allowedConfigurations?: list<class-string<ConfigurationNode>>|null, constraints?: list<Closure(ComponentInterface): (iterable<string>|string|null)>}  $changes
     */
    private function copy(array $changes): self
    {
        return new self(
            type: $changes['type'] ?? $this->type,
            properties: $changes['properties'] ?? array_values($this->properties),
            allowsUnknownProperties: $changes['allowsUnknownProperties'] ?? $this->allowsUnknownProperties,
            allowsChildren: $changes['allowsChildren'] ?? $this->allowsChildren,
            allowedChildren: array_key_exists('allowedChildren', $changes) ? $changes['allowedChildren'] : $this->allowedChildren,
            minChildren: $changes['minChildren'] ?? $this->minChildren,
            maxChildren: array_key_exists('maxChildren', $changes) ? $changes['maxChildren'] : $this->maxChildren,
            allowedConfigurations: array_key_exists('allowedConfigurations', $changes) ? $changes['allowedConfigurations'] : $this->allowedConfigurations,
            constraints: $changes['constraints'] ?? $this->constraints,
        );
    }

    /**
     * @template T of object
     *
     * @param  array<mixed>  $classes
     * @param  class-string<T>  $contract
     * @return list<class-string<T>>
     */
    private static function assertClasses(string $type, array $classes, string $contract): array
    {
        $valid = [];

        foreach ($classes as $class) {
            if (! is_string($class) || ! is_a($class, $contract, true)) {
                throw InvalidConfigurationException::invalidDefinition(sprintf(
                    'Allowed class "%s" on "%s" must be an existing class or interface implementing %s.',
                    is_string($class) ? ValueDescriber::className($class) : get_debug_type($class),
                    $type,
                    $contract,
                ));
            }

            $valid[] = $class;
        }

        return $valid;
    }

    /**
     * @param  list<class-string>|null  $classes
     */
    private static function matchesAny(object $candidate, ?array $classes): bool
    {
        if ($classes === null) {
            return true;
        }

        foreach ($classes as $class) {
            if ($candidate instanceof $class) {
                return true;
            }
        }

        return false;
    }
}
