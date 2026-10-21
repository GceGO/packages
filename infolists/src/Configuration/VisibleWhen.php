<?php

declare(strict_types=1);

namespace Gcego\Infolists\Configuration;

use Closure;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Infolists\Entries\Entry;
use Gcego\Infolists\Exceptions\InvalidEntryException;
use Gcego\Infolists\Support\RecordEvaluation;
use Gcego\Tables\Support\Formats;
use Gcego\Tables\Support\ValueResolver;
use UnitEnum;

/**
 * Show an entry or layout only for some records, evaluated on the server
 * against the authorized record:
 *
 *     new Text('archived_reason', new VisibleWhen('status', 'archived'))
 *     new Section('Grading', ..., new VisibleWhen('is_graded'))              // truthy
 *     new Text('notes', VisibleWhen::filled('notes'))
 *     new Text('code', VisibleWhen::not('status', 'draft'))
 *     new Section('Late', ..., VisibleWhen::using(fn (Subject $subject) => $subject->due_on?->isPast()))
 *     VisibleWhen::computed(fn (Subject $record, ?User $user): bool => $user?->is($record->owner) ?? false)
 *
 * Visibility never grants access: Access rules still apply, and a condition
 * on a path the user may not read evaluates to false.
 */
final readonly class VisibleWhen implements ConfigurationNode
{
    public function __construct(
        public ?string $path,
        public mixed $value = true,
        public string $operator = 'equals',
        public ?Closure $callback = null,
    ) {
        if ($path !== null && preg_match(Entry::NamePattern, $path) !== 1) {
            throw InvalidEntryException::invalid('VisibleWhen', sprintf('"%s" is not a path of the record.', mb_substr($path, 0, 64)));
        }
    }

    public static function make(string $path, mixed $value = true): self
    {
        return new self($path, $value);
    }

    public static function filled(string $path): self
    {
        return new self($path, operator: 'filled');
    }

    public static function blank(string $path): self
    {
        return new self($path, operator: 'blank');
    }

    public static function not(string $path, mixed $value): self
    {
        return new self($path, $value, 'not');
    }

    /**
     * @param  Closure(mixed): bool  $callback
     */
    public static function using(Closure $callback): self
    {
        return new self(null, operator: 'using', callback: $callback);
    }

    /**
     * A Filament-style closure injected with $record (also by model type) and $user:
     * fn (Subject $record): bool => $record->is_graded.
     *
     * @param  bool  $negate  Pass when the closure does not return true (hidden(fn)).
     */
    public static function computed(Closure $closure, bool $negate = false): self
    {
        return self::using(static function (mixed $record) use ($closure, $negate): bool {
            $result = RecordEvaluation::call($closure, $record, subject: 'an infolist visibility closure');

            return $negate ? $result !== true : $result === true;
        });
    }

    /**
     * @param  Closure(string): bool  $readable  Whether the user may read a path.
     */
    public function passes(mixed $record, Closure $readable): bool
    {
        if ($this->callback !== null) {
            return ($this->callback)($record) === true;
        }

        if ($this->path === null || ! $readable($this->path)) {
            return false;
        }

        $actual = ValueResolver::get($record, $this->path);
        $filled = $actual !== null && $actual !== '' && $actual !== [] && ! ($actual instanceof \Countable && count($actual) === 0);

        return match ($this->operator) {
            'filled' => $filled,
            'blank' => ! $filled,
            'not' => ! self::matches($actual, $this->value),
            default => self::matches($actual, $this->value),
        };
    }

    private static function matches(mixed $actual, mixed $expected): bool
    {
        if ($expected === true) {
            return (bool) ($actual instanceof UnitEnum ? true : $actual);
        }

        $actual = $actual instanceof \BackedEnum ? $actual->value : $actual;

        foreach (is_array($expected) ? $expected : [$expected] as $candidate) {
            $candidate = $candidate instanceof \BackedEnum ? $candidate->value : $candidate;

            if (Formats::text($actual) === Formats::text($candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{path: string|null, operator: string}
     */
    public function normalize(): array
    {
        return ['path' => $this->path, 'operator' => $this->operator];
    }
}
