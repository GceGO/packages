<?php

declare(strict_types=1);

namespace Gcego\Components;

use Gcego\Components\Exceptions\InvalidConfigurationException;
use UnitEnum;

/**
 * Describes the accepted type of a property value.
 *
 * Checks are strict (no coercion) with one exception: float also accepts int.
 * Class-string checks autoload the named class but never instantiate it.
 */
final readonly class Type
{
    /**
     * @param  class-string|null  $class
     */
    private function __construct(
        public TypeKind $kind,
        public ?string $class = null,
    ) {}

    public static function string(): self
    {
        return new self(TypeKind::String);
    }

    public static function int(): self
    {
        return new self(TypeKind::Int);
    }

    public static function float(): self
    {
        return new self(TypeKind::Float);
    }

    public static function bool(): self
    {
        return new self(TypeKind::Bool);
    }

    /**
     * Any array of supported property values.
     */
    public static function array(): self
    {
        return new self(TypeKind::Array);
    }

    /**
     * An array with sequential integer keys starting at zero.
     */
    public static function list(): self
    {
        return new self(TypeKind::List);
    }

    /**
     * Any value the Property value object supports.
     */
    public static function mixed(): self
    {
        return new self(TypeKind::Mixed);
    }

    /**
     * A case of the given enum.
     *
     * @param  class-string<UnitEnum>  $enum
     */
    public static function enum(string $enum): self
    {
        if (! enum_exists($enum)) {
            throw InvalidConfigurationException::invalidDefinition(sprintf('"%s" is not an enum.', $enum));
        }

        return new self(TypeKind::Enum, $enum);
    }

    /**
     * The name of an existing class or interface, optionally a subtype of $parent.
     *
     * @param  class-string|null  $parent
     */
    public static function classString(?string $parent = null): self
    {
        if ($parent !== null && ! class_exists($parent) && ! interface_exists($parent)) {
            throw InvalidConfigurationException::invalidDefinition(sprintf('Class or interface "%s" does not exist.', $parent));
        }

        return new self(TypeKind::ClassString, $parent);
    }

    /**
     * An instance of the given class, typically a ValueObject or RuntimeValue.
     *
     * @param  class-string  $class
     */
    public static function instanceOf(string $class): self
    {
        if (! class_exists($class) && ! interface_exists($class)) {
            throw InvalidConfigurationException::invalidDefinition(sprintf('Class or interface "%s" does not exist.', $class));
        }

        return new self(TypeKind::Instance, $class);
    }

    public function accepts(mixed $value): bool
    {
        return match ($this->kind) {
            TypeKind::String => is_string($value),
            TypeKind::Int => is_int($value),
            TypeKind::Float => is_float($value) || is_int($value),
            TypeKind::Bool => is_bool($value),
            TypeKind::Array => is_array($value),
            TypeKind::List => is_array($value) && array_is_list($value),
            TypeKind::Mixed => true,
            TypeKind::Enum, TypeKind::Instance => $this->class !== null && $value instanceof $this->class,
            TypeKind::ClassString => is_string($value)
                && (class_exists($value) || interface_exists($value))
                && ($this->class === null || is_a($value, $this->class, true)),
        };
    }

    public function describe(): string
    {
        return match ($this->kind) {
            TypeKind::Enum => 'enum<'.$this->class.'>',
            TypeKind::Instance => (string) $this->class,
            TypeKind::ClassString => $this->class === null ? 'class-string' : 'class-string<'.$this->class.'>',
            default => $this->kind->value,
        };
    }
}
