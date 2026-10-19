<?php

declare(strict_types=1);

namespace Gcego\Layouts\Responsive;

use Closure;
use Gcego\Layouts\Exceptions\InvalidLayoutException;

/**
 * A value that may change at breakpoints, mobile first.
 *
 *     ResponsiveValue::of(3, …)                              // 3 at every width
 *     ResponsiveValue::of(['default' => 1, 'md' => 2, 'xl' => 4], …)
 *
 * Keys must be breakpoint names; every value is checked by the caller's
 * rule. A breakpoint without a value inherits the nearest smaller one
 * (precedence: larger breakpoints override smaller ones from their width up).
 */
final readonly class ResponsiveValue
{
    /**
     * @param  array<string, int|string>  $values  Defined values by breakpoint name, in breakpoint order.
     */
    private function __construct(public array $values) {}

    /**
     * @param  Closure(mixed): bool  $accepts
     */
    public static function of(mixed $value, Closure $accepts, string $subject, string $expected): self
    {
        $values = is_array($value) ? $value : [Breakpoint::Default->value => $value];

        if ($values === []) {
            throw InvalidLayoutException::invalid($subject, 'it needs a value for at least one breakpoint.');
        }

        $defined = [];

        foreach ($values as $key => $item) {
            $breakpoint = Breakpoint::parse($key, $subject);

            if ((! is_int($item) && ! is_string($item)) || ! $accepts($item)) {
                throw InvalidLayoutException::invalid($subject, sprintf('the value at "%s" must be %s.', $breakpoint->value, $expected));
            }

            $defined[$breakpoint->value] = $item;
        }

        $ordered = [];

        foreach (Breakpoint::cases() as $breakpoint) {
            if (array_key_exists($breakpoint->value, $defined)) {
                $ordered[$breakpoint->value] = $defined[$breakpoint->value];
            }
        }

        return new self($ordered);
    }

    /**
     * The value in effect at a breakpoint, or null below the first defined one.
     */
    public function at(Breakpoint $breakpoint): int|string|null
    {
        $value = null;

        foreach (Breakpoint::cases() as $candidate) {
            if (array_key_exists($candidate->value, $this->values)) {
                $value = $this->values[$candidate->value];
            }

            if ($candidate === $breakpoint) {
                return $value;
            }
        }

        return $value;
    }

    /**
     * The value in effect at every breakpoint.
     *
     * @return array<string, int|string|null>
     */
    public function resolved(): array
    {
        $resolved = [];

        foreach (Breakpoint::cases() as $breakpoint) {
            $resolved[$breakpoint->value] = $this->at($breakpoint);
        }

        return $resolved;
    }

    public function isResponsive(): bool
    {
        return array_keys($this->values) !== [Breakpoint::Default->value];
    }

    /**
     * Classes for the breakpoints where the value changes, from a class map
     * keyed by breakpoint and value (a map of literal classes, so Tailwind
     * finds every one).
     *
     * @param  array<string, array<int|string, string>>  $map
     */
    public function classes(array $map): string
    {
        $classes = [];
        $previous = null;

        foreach ($this->resolved() as $breakpoint => $value) {
            if ($value !== null && $value !== $previous && isset($map[$breakpoint][$value])) {
                $classes[] = $map[$breakpoint][$value];
            }

            $previous = $value ?? $previous;
        }

        return implode(' ', $classes);
    }

    /**
     * @return int|string|array<string, int|string>
     */
    public function normalize(): int|string|array
    {
        return $this->isResponsive() ? $this->values : $this->values[Breakpoint::Default->value];
    }
}
