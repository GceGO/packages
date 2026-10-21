<?php

declare(strict_types=1);

namespace Gcego\Components\Schema;

use Closure;
use Gcego\Components\Support\Absent;
use UnitEnum;

/** Immutable fluent builder for a property definition, distinct from a form control. */
final readonly class Field
{
    private function __construct(private PropertyDefinition $definition) {}

    /** @param Type|list<Type> $type */
    public static function of(string $name, Type|array $type): self
    {
        return new self(new PropertyDefinition($name, $type));
    }

    public static function string(string $name): self
    {
        return self::of($name, Type::string());
    }

    public static function boolean(string $name): self
    {
        return self::of($name, Type::bool());
    }

    public static function integer(string $name): self
    {
        return self::of($name, Type::int());
    }

    public static function number(string $name): self
    {
        return self::of($name, Type::float());
    }

    public static function array(string $name): self
    {
        return self::of($name, Type::array());
    }

    public static function list(string $name): self
    {
        return self::of($name, Type::list());
    }

    public static function mixed(string $name): self
    {
        return self::of($name, Type::mixed());
    }

    public function mustExist(bool $required = true): self
    {
        return $this->copy(required: $required);
    }

    public function nullable(bool $nullable = true): self
    {
        return $this->copy(nullable: $nullable);
    }

    public function defaultsTo(mixed $value): self
    {
        return $this->copy(default: $value);
    }

    /** @param list<scalar|UnitEnum> $values */
    public function allowedValues(array $values): self
    {
        return $this->copy(allowedValues: $values);
    }

    /** @param Closure(mixed): bool $rule */
    public function rule(Closure $rule, ?string $description = null): self
    {
        return $this->copy(rule: $rule, ruleDescription: $description ?? $this->definition->ruleDescription);
    }

    /** @param Closure(mixed): mixed $normalizer */
    public function normalizeUsing(Closure $normalizer): self
    {
        return $this->copy(normalizer: $normalizer);
    }

    public function description(string $description): self
    {
        return $this->copy(description: $description);
    }

    public function build(): PropertyDefinition
    {
        return $this->definition;
    }

    /**
     * @param  list<scalar|UnitEnum>|null  $allowedValues
     * @param  (Closure(mixed): bool)|null  $rule
     * @param  (Closure(mixed): mixed)|null  $normalizer
     */
    private function copy(
        ?bool $required = null,
        ?bool $nullable = null,
        mixed $default = Absent::Value,
        ?array $allowedValues = null,
        ?Closure $rule = null,
        ?string $ruleDescription = null,
        ?Closure $normalizer = null,
        ?string $description = null,
    ): self {
        $definition = $this->definition;

        return new self(new PropertyDefinition(
            name: $definition->name,
            type: $definition->types,
            required: $required ?? $definition->required,
            nullable: $nullable ?? $definition->nullable,
            default: $default !== Absent::Value ? $default : ($definition->hasDefault ? $definition->default : Absent::Value),
            allowedValues: $allowedValues ?? $definition->allowedValues,
            rule: $rule ?? $definition->rule,
            ruleDescription: $ruleDescription ?? $definition->ruleDescription,
            normalizer: $normalizer ?? $definition->normalizer,
            description: $description ?? $definition->description,
        ));
    }
}
