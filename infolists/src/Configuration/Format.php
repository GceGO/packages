<?php

declare(strict_types=1);

namespace Gcego\Infolists\Configuration;

use Closure;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Infolists\Contracts\EntryFormatter;
use Gcego\Infolists\Entries\Entry;
use Gcego\Infolists\Exceptions\InvalidEntryException;
use Gcego\Infolists\Support\RecordEvaluation;

/**
 * Custom entry text, always escaped:
 *
 *     new Text('score', 'Score', new Format(fn (?int $score, Result $result) => $score === null ? null : $score.' / '.$result->max))
 *     new Text('grade', 'Grade', new Format(GradeFormatter::class))
 */
final readonly class Format implements ConfigurationNode
{
    /**
     * @param  Closure|class-string<EntryFormatter>  $formatter  fn (mixed $value, mixed $record): string|int|float|null
     * @param  bool  $injected  Inject closure parameters by name and type ($state, $record, $entry, Subject $subject) instead of passing ($value, $record).
     */
    public function __construct(public Closure|string $formatter, public bool $injected = false)
    {
        if (is_string($formatter) && ! is_subclass_of($formatter, EntryFormatter::class)) {
            throw InvalidEntryException::invalid('Format', sprintf('"%s" must implement %s.', mb_substr($formatter, 0, 120), EntryFormatter::class));
        }
    }

    public function apply(mixed $value, mixed $record, Entry $entry): ?string
    {
        if ($this->formatter instanceof Closure && $this->injected) {
            $result = RecordEvaluation::call($this->formatter, $record, ['state' => $value, 'entry' => $entry], $entry, sprintf('formatStateUsing() of entry "%s"', $entry->entryName()));
        } elseif ($this->formatter instanceof Closure) {
            $result = ($this->formatter)($value, $record);
        } else {
            $formatter = app($this->formatter);
            $result = $formatter instanceof EntryFormatter ? $formatter->format($value, $record, $entry) : null;
        }

        return is_scalar($result) ? (string) $result : null;
    }

    /**
     * @return array{formatter: string}
     */
    public function normalize(): array
    {
        return ['formatter' => $this->formatter instanceof Closure ? 'closure' : $this->formatter];
    }
}
