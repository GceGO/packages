<?php

declare(strict_types=1);

namespace Gcego\Forms\Options;

use ArrayIterator;
use BackedEnum;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * An ordered set of options; the authority on which values may be submitted.
 *
 * @implements IteratorAggregate<int, Option>
 */
final readonly class OptionCollection implements Countable, IteratorAggregate
{
    /**
     * @param  list<Option>  $options
     */
    public function __construct(public array $options = []) {}

    /**
     * @param  array<array-key, mixed>|iterable<Option>  $options  value => label, or Option objects.
     */
    public static function from(iterable $options): self
    {
        $list = [];

        foreach ($options as $value => $label) {
            $list[] = match (true) {
                $label instanceof Option => $label,
                $label instanceof BackedEnum => new Option($label->value, method_exists($label, 'label') && is_scalar($text = $label->label()) ? (string) $text : $label->name),
                is_scalar($label) && (is_int($value) || is_string($value)) => new Option($value, (string) $label),
                default => null,
            };
        }

        return new self(array_values(array_filter($list)));
    }

    /**
     * Whether the value is an enabled option (compared as strings: browsers send strings).
     */
    public function contains(mixed $value): bool
    {
        if (! is_scalar($value)) {
            return false;
        }

        foreach ($this->options as $option) {
            if (! $option->disabled && (string) $option->value === (string) $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * The option's own value (int or string) for a submitted string.
     */
    public function valueFor(mixed $value): string|int|null
    {
        foreach ($this->options as $option) {
            if (is_scalar($value) && (string) $option->value === (string) $value) {
                return $option->value;
            }
        }

        return null;
    }

    /**
     * @return list<string|int>
     */
    public function values(): array
    {
        return array_map(static fn (Option $option): string|int => $option->value, array_values(array_filter($this->options, static fn (Option $option): bool => ! $option->disabled)));
    }

    public function count(): int
    {
        return count($this->options);
    }

    /**
     * @return Traversable<int, Option>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->options);
    }
}
