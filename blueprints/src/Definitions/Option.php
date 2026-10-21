<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Definitions;

use Gcego\Blueprints\Exceptions\InvalidDefinitionException;
use Gcego\Blueprints\Support\ClassReferences;
use Gcego\Blueprints\Support\Naming;

/**
 * One typed, validated option of an OptionsDefinition.
 *
 *     'group' => Option::text(required: true),
 *     'icon'  => Option::icon(),
 *     'sort'  => Option::integer(min: 0, default: 0),
 */
final readonly class Option
{
    public const string Text = 'text';

    public const string Identifier = 'identifier';

    public const string Identifiers = 'identifiers';

    public const string Icon = 'icon';

    public const string Integer = 'integer';

    public const string Boolean = 'boolean';

    public const string ClassName = 'class';

    private function __construct(
        public string $type,
        public bool $required = false,
        public string|int|bool|null $default = null,
        public ?int $min = null,
        public ?int $max = null,
    ) {}

    /** A non-empty, trimmed string of at most 200 characters. */
    public static function text(bool $required = false, ?string $default = null): self
    {
        return new self(self::Text, $required, $default);
    }

    /** A lowercase identifier such as "admin" or "subjects.export". */
    public static function identifier(bool $required = false, ?string $default = null): self
    {
        return new self(self::Identifier, $required, $default);
    }

    /** A non-empty list of distinct identifiers. */
    public static function identifiers(bool $required = false): self
    {
        return new self(self::Identifiers, $required);
    }

    /** An icon identifier such as "academic-cap" or "lucide:book-open". */
    public static function icon(bool $required = false, ?string $default = null): self
    {
        return new self(self::Icon, $required, $default);
    }

    public static function integer(bool $required = false, ?int $default = null, ?int $min = null, ?int $max = null): self
    {
        return new self(self::Integer, $required, $default, $min, $max);
    }

    public static function boolean(bool $default = false): self
    {
        return new self(self::Boolean, false, $default);
    }

    /** A class reference, stored without autoloading and checked by the reference rules. */
    public static function className(bool $required = false): self
    {
        return new self(self::ClassName, $required);
    }

    /**
     * The validated, normalized value of a present option.
     *
     * @return string|int|bool|list<string>
     *
     * @throws InvalidDefinitionException
     */
    public function normalize(string $key, string $name, mixed $value): string|int|bool|array
    {
        $fail = static fn (string $expected): InvalidDefinitionException => InvalidDefinitionException::because(
            $key,
            sprintf('option "%s": expected %s, got %s.', $name, $expected, is_scalar($value) ? var_export($value, true) : get_debug_type($value)),
        );

        return match ($this->type) {
            self::Text => is_string($value) && Naming::isTitle(trim($value)) ? trim($value) : throw $fail('non-empty text of at most '.Naming::MaxTitleLength.' characters'),
            self::Identifier => Naming::isIdentifier($value) && is_string($value) ? $value : throw $fail('a lowercase identifier such as "admin"'),
            self::Icon => Naming::isIcon($value) && is_string($value) ? $value : throw $fail('an icon identifier such as "academic-cap"'),
            self::Identifiers => is_array($value) ? IdentifierListDefinition::normalize($key, $value, $name) : throw $fail('a list of identifiers'),
            self::Integer => is_int($value) && ($this->min === null || $value >= $this->min) && ($this->max === null || $value <= $this->max)
                ? $value
                : throw $fail($this->integerRange()),
            self::Boolean => is_bool($value) ? $value : throw $fail('true or false'),
            default => ClassReferences::one($key, $value, sprintf('option "%s":', $name)),
        };
    }

    private function integerRange(): string
    {
        return match (true) {
            $this->min !== null && $this->max !== null => sprintf('an integer from %d to %d', $this->min, $this->max),
            $this->min !== null => sprintf('an integer of at least %d', $this->min),
            $this->max !== null => sprintf('an integer of at most %d', $this->max),
            default => 'an integer',
        };
    }
}
