<?php

declare(strict_types=1);

namespace Gcego\Components\Schema;

use Closure;
use Gcego\Components\Evaluation\Computed;
use Gcego\Components\Exceptions\InvalidConfigurationException;
use Gcego\Components\Facades\Components;
use Gcego\Components\Properties\Property;
use Gcego\Components\Support\Absent;
use Gcego\Components\Support\ValueDescriber;
use Gcego\Components\Support\ValueSnapshot;
use UnitEnum;

/**
 * Schema metadata for one property of a component.
 *
 * An absent property and a property explicitly set to null are different:
 * absence falls back to the default (when declared) and fails "required";
 * an explicit null is only valid when the definition is nullable.
 */
final readonly class PropertyDefinition
{
    /**
     * @var list<Type>
     */
    public array $types;

    public bool $hasDefault;

    public mixed $default;

    /**
     * @var list<scalar|UnitEnum>|null
     */
    public ?array $allowedValues;

    /**
     * @param  Type|list<Type>  $type  One type or a union of types.
     * @param  mixed  $default  Omit for "no default"; null is a real default.
     * @param  list<scalar|UnitEnum>|null  $allowedValues  Strictly compared whitelist.
     * @param  (Closure(mixed): bool)|null  $rule  Extra check run after type checks.
     * @param  string|null  $ruleDescription  Human-readable expectation shown when $rule fails.
     * @param  (Closure(mixed): mixed)|null  $normalizer  Applied to the effective value during normalization only.
     */
    public function __construct(
        public string $name,
        Type|array $type,
        public bool $required = false,
        public bool $nullable = false,
        mixed $default = Absent::Value,
        ?array $allowedValues = null,
        public ?Closure $rule = null,
        public ?string $ruleDescription = null,
        public ?Closure $normalizer = null,
        public ?string $description = null,
    ) {
        if (preg_match(Property::KeyPattern, $name) !== 1) {
            throw InvalidConfigurationException::invalidDefinition(sprintf('"%s" is not a valid property name.', $name));
        }

        $types = $type instanceof Type ? [$type] : $type;

        if ($types === []) {
            throw InvalidConfigurationException::invalidDefinition(sprintf('Property "%s" must declare at least one type.', $name));
        }

        $typeSnapshot = [];

        foreach ($types as $candidate) {
            if (! $candidate instanceof Type) {
                throw InvalidConfigurationException::invalidDefinition(sprintf('Property "%s" types must be %s instances.', $name, Type::class));
            }

            $typeSnapshot[] = $candidate;
        }

        $this->types = $typeSnapshot;
        $this->hasDefault = $default !== Absent::Value;
        $allowedSnapshot = [];

        foreach ($allowedValues ?? [] as $allowed) {
            if (! is_scalar($allowed) && ! $allowed instanceof UnitEnum) {
                throw InvalidConfigurationException::invalidDefinition(sprintf('Allowed values of property "%s" must be scalars or enum cases.', $name));
            }

            $allowedSnapshot[] = $allowed;
        }

        $this->allowedValues = $allowedValues === null ? null : $allowedSnapshot;

        if ($required && $this->hasDefault) {
            throw InvalidConfigurationException::invalidDefinition(sprintf('Property "%s" cannot be required and declare a default at the same time.', $name));
        }

        if ($this->hasDefault) {
            $this->assertValidDefault($default);
        }

        $this->default = $this->hasDefault ? ValueSnapshot::copy($default) : null;
    }

    /**
     * Whether the property holds a callback (such as afterStateUpdated) that
     * is invoked by a runtime, rather than a computed value to evaluate.
     */
    public function isCallback(): bool
    {
        return count($this->types) === 1
            && $this->types[0]->kind === TypeKind::Instance
            && $this->types[0]->class === Computed::class;
    }

    public function acceptsType(mixed $value): bool
    {
        if ($value === null) {
            return $this->nullable;
        }

        foreach ($this->types as $type) {
            if ($type->accepts($value)) {
                return true;
            }
        }

        return false;
    }

    public function allowsValue(mixed $value): bool
    {
        return $this->allowedValues === null || in_array($value, $this->allowedValues, true);
    }

    public function satisfiesRule(mixed $value): bool
    {
        return $this->rule === null || ($this->rule)($value) === true;
    }

    public function expectedType(): string
    {
        $descriptions = array_map(static fn (Type $type): string => $type->describe(), $this->types);

        if ($this->nullable) {
            $descriptions[] = 'null';
        }

        return implode('|', $descriptions);
    }

    public function expectedValues(): string
    {
        return 'one of '.implode(', ', array_map(
            static fn (mixed $value): string => ValueDescriber::describe($value),
            $this->allowedValues ?? [],
        ));
    }

    public function expectedRule(): string
    {
        return $this->ruleDescription ?? 'a value accepted by the custom rule';
    }

    /**
     * Schema metadata. The rule and normalizer closures are reported as flags only.
     *
     * @return array{name: string, types: list<string>, required: bool, nullable: bool, hasDefault: bool, default: mixed, allowedValues: list<scalar|UnitEnum>|null, hasRule: bool, ruleDescription: string|null, hasNormalizer: bool, description: string|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'types' => array_map(static fn (Type $type): string => $type->describe(), $this->types),
            'required' => $this->required,
            'nullable' => $this->nullable,
            'hasDefault' => $this->hasDefault,
            'default' => Components::normalizeValue($this->default, sprintf('schema property "%s" default', $this->name)),
            'allowedValues' => $this->allowedValues,
            'hasRule' => $this->rule !== null,
            'ruleDescription' => $this->ruleDescription,
            'hasNormalizer' => $this->normalizer !== null,
            'description' => $this->description,
        ];
    }

    private function assertValidDefault(mixed $default): void
    {
        $reason = match (true) {
            ! Property::isSupportedValue($default) => 'is not a supported property value',
            ! $this->acceptsType($default) => 'does not match '.$this->expectedType(),
            $default !== null && ! $this->allowsValue($default) => 'is not '.$this->expectedValues(),
            $default !== null && ! $this->satisfiesRule($default) => 'is not '.$this->expectedRule(),
            default => null,
        };

        if ($reason !== null) {
            throw InvalidConfigurationException::invalidDefinition(sprintf(
                'Default %s of property "%s" %s.',
                ValueDescriber::describe($default),
                $this->name,
                $reason,
            ));
        }
    }
}
