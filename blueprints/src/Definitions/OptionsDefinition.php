<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Definitions;

use Gcego\Blueprints\Contracts\ReferencesClasses;
use Gcego\Blueprints\Exceptions\InvalidDefinitionException;

/**
 * A definition holding typed, validated options, for extension packages
 * whose capability is data rather than a class:
 *
 *     final readonly class Navigation extends OptionsDefinition
 *     {
 *         public const string Key = 'navigation';
 *
 *         public static function definitionKey(): string { return self::Key; }
 *
 *         public static function schema(): array
 *         {
 *             return ['group' => Option::text(required: true), 'icon' => Option::icon(), 'sort' => Option::integer(default: 0, min: 0)];
 *         }
 *
 *         public static function make(string $group, ?string $icon = null, int $sort = 0): self
 *         {
 *             return self::fromOptions(['group' => $group, 'icon' => $icon, 'sort' => $sort]);
 *         }
 *     }
 *
 * Give subclasses a make() with named, typed parameters, and build with
 * fromOptions(). Unknown options are rejected; a null option is absent (its
 * default applies, or it is reported when required). The value lists every
 * schema option in schema order. Class options are references, checked by
 * the reference rules like Table::make(...).
 */
abstract readonly class OptionsDefinition implements ReferencesClasses
{
    /**
     * @param  array<string, string|int|bool|list<string>|null>  $options
     */
    final protected function __construct(private array $options) {}

    /**
     * @return array<string, Option> In the order values are stored.
     */
    abstract public static function schema(): array;

    /**
     * @param  array<string, mixed>  $options
     *
     * @throws InvalidDefinitionException
     */
    final protected static function fromOptions(array $options): static
    {
        $key = static::definitionKey();
        $schema = static::schema();
        $unknown = array_diff(array_keys($options), array_keys($schema));

        if ($unknown !== []) {
            throw InvalidDefinitionException::because($key, sprintf(
                'unknown option(s) %s. Known options: %s.',
                implode(', ', array_map(static fn (int|string $name): string => '"'.$name.'"', $unknown)),
                implode(', ', array_keys($schema)),
            ));
        }

        $normalized = [];

        foreach ($schema as $name => $option) {
            $value = $options[$name] ?? null;

            if ($value === null) {
                if ($option->required) {
                    throw InvalidDefinitionException::because($key, sprintf('option "%s" is required.', $name));
                }

                $normalized[$name] = $option->default;

                continue;
            }

            $normalized[$name] = $option->normalize($key, $name, $value);
        }

        return new static($normalized);
    }

    final public function key(): string
    {
        return static::definitionKey();
    }

    /**
     * @return array<string, string|int|bool|list<string>|null>
     */
    public function value(): array
    {
        return $this->options;
    }

    /**
     * @return string|int|bool|list<string>|null
     */
    public function option(string $name): string|int|bool|array|null
    {
        return $this->options[$name] ?? null;
    }

    public function classes(): array
    {
        $classes = [];

        foreach (static::schema() as $name => $option) {
            $value = $this->options[$name] ?? null;

            if ($option->type === Option::ClassName && is_string($value)) {
                /** @var class-string $value */
                $classes[] = $value;
            }
        }

        return $classes;
    }
}
