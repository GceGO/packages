<?php

declare(strict_types=1);

namespace Gcego\Forms\Configuration;

use BackedEnum;
use Closure;
use Gcego\Forms\Exceptions\InvalidFieldException;
use Gcego\Forms\Reactive\FormEvaluation;
use Gcego\Forms\State\FormState;
use Gcego\Forms\State\StatePath;

/**
 * A trusted, server-side condition over form state:
 *
 *     Condition::make('category', 'science')        // equals
 *     Condition::make('level', [1, 2])              // in
 *     Condition::filled('code')
 *     Condition::blank('code')
 *     Condition::not('category', 'arts')
 *     Condition::using(fn (FormState $state) => ..., dependsOn: ['category'])
 *     Condition::computed(fn (Get $get): bool => $get('category') === 'science')
 *
 * Inside a repeater, a name of a sibling field refers to the same item.
 * Conditions are evaluated on the server only; the browser never executes them.
 */
class Condition
{
    final public const string Equals = 'equals';

    final public const string NotEquals = 'not';

    final public const string Filled = 'filled';

    final public const string Blank = 'blank';

    final public const string Callback = 'callback';

    /**
     * @param  list<string>  $dependsOn
     */
    final private function __construct(
        public readonly ?string $field,
        public readonly string $operator,
        public readonly mixed $value = null,
        public readonly ?Closure $callback = null,
        public readonly array $dependsOn = [],
    ) {
        if ($field !== null) {
            StatePath::assertName($field);
        }
    }

    /**
     * @param  scalar|BackedEnum|list<scalar|BackedEnum>|null  $value
     */
    public static function make(string $field, mixed $value): static
    {
        return new static($field, self::Equals, self::scalar($value));
    }

    public static function filled(string $field): static
    {
        return new static($field, self::Filled);
    }

    public static function blank(string $field): static
    {
        return new static($field, self::Blank);
    }

    /**
     * @param  scalar|BackedEnum|list<scalar|BackedEnum>|null  $value
     */
    public static function not(string $field, mixed $value): static
    {
        return new static($field, self::NotEquals, self::scalar($value));
    }

    /**
     * @param  Closure(FormState, ?string): bool  $callback  Receives the state and the repeater item path, if any.
     * @param  list<string>  $dependsOn  Fields whose changes re-evaluate the condition.
     */
    public static function using(Closure $callback, array $dependsOn = []): static
    {
        foreach ($dependsOn as $field) {
            StatePath::assertName($field);
        }

        return new static(null, self::Callback, callback: $callback, dependsOn: array_values($dependsOn));
    }

    /**
     * A Filament-style closure, injected with $get, $set, $record, $operation
     * and typed values. The fields it reads are discovered at runtime, so no
     * dependsOn is needed: they are rendered live automatically.
     *
     * @param  bool  $negate  Pass when the closure does not return true (hidden(fn) becomes a visibility condition).
     */
    public static function computed(Closure $closure, bool $negate = false): static
    {
        return static::using(static function (FormState $state, ?string $itemPath) use ($closure, $negate): bool {
            $result = app(FormEvaluation::class)->evaluate($closure, $state, $itemPath);

            return $negate ? $result !== true : $result === true;
        });
    }

    /**
     * @param  list<string>  $siblings  Field names of the enclosing repeater item.
     */
    public function passes(FormState $state, ?string $itemPath = null, array $siblings = []): bool
    {
        if ($this->operator === self::Callback) {
            return ($this->callback ?? static fn (): bool => false)($state, $itemPath) === true;
        }

        $path = $itemPath !== null && in_array($this->field, $siblings, true) ? $itemPath.'.'.$this->field : (string) $this->field;
        $actual = $state->get($path);

        return match ($this->operator) {
            self::Filled => $state->filled($path),
            self::Blank => ! $state->filled($path),
            self::NotEquals => ! self::matches($actual, $this->value),
            default => self::matches($actual, $this->value),
        };
    }

    /**
     * @return list<string>
     */
    public function dependencies(): array
    {
        return $this->field === null ? $this->dependsOn : [$this->field];
    }

    public function describe(): string
    {
        return match ($this->operator) {
            self::Callback => $this->dependsOn === [] ? 'callback' : 'callback on '.implode(', ', $this->dependsOn),
            self::Filled, self::Blank => $this->field.' is '.$this->operator,
            default => $this->field.($this->operator === self::NotEquals ? ' is not ' : ' is ').(string) json_encode($this->value),
        };
    }

    private static function matches(mixed $actual, mixed $expected): bool
    {
        $values = is_array($expected) ? $expected : [$expected];

        foreach ($values as $candidate) {
            $matched = match (true) {
                is_array($actual) => in_array(self::text($candidate), array_map(self::text(...), $actual), true),
                is_bool($candidate) => (bool) $actual === $candidate,
                default => (is_scalar($actual) || $actual === null) && self::text($actual) === self::text($candidate),
            };

            if ($matched) {
                return true;
            }
        }

        return false;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function scalar(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            is_array($value) => array_values(array_map(static fn (mixed $item): mixed => $item instanceof BackedEnum ? $item->value : (is_scalar($item) ? $item : throw InvalidFieldException::invalid('Condition', 'values must be scalars.')), $value)),
            $value === null || is_scalar($value) => $value,
            default => throw InvalidFieldException::invalid('Condition', 'values must be scalars.'),
        };
    }
}
