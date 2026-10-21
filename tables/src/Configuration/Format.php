<?php

declare(strict_types=1);

namespace Gcego\Tables\Configuration;

use Closure;
use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Components\Evaluation\EvaluationContext;
use Gcego\Components\Facades\Components;
use Gcego\Tables\Columns\Column;
use Gcego\Tables\Contracts\ColumnFormatter;
use Gcego\Tables\Exceptions\InvalidTableException;

/**
 * Custom cell text for a column:
 *
 *     new Text('score', 'Score', new Format(fn (?int $score, Result $result) => $score === null ? null : $score.' / '.$result->max))
 *     new Text('grade', 'Grade', new Format(GradeFormatter::class))
 *
 * The result is plain text and is always escaped; it can never produce HTML.
 */
final readonly class Format implements ConfigurationNode
{
    /**
     * @param  Closure|class-string<ColumnFormatter>  $formatter  fn (mixed $value, mixed $record): string|int|float|null
     * @param  bool  $injected  Inject closure parameters by name and type ($state, $record, $column, Subject $subject) instead of passing ($value, $record).
     */
    public function __construct(public Closure|string $formatter, public bool $injected = false)
    {
        if (is_string($formatter) && ! is_subclass_of($formatter, ColumnFormatter::class)) {
            throw InvalidTableException::invalid('Format', sprintf('"%s" must implement %s.', mb_substr($formatter, 0, 120), ColumnFormatter::class));
        }
    }

    public function apply(mixed $value, mixed $record, Column $column): ?string
    {
        if ($this->formatter instanceof Closure && $this->injected) {
            $context = EvaluationContext::make(['state' => $value, 'record' => $record, 'column' => $column])->withTyped(...(is_object($record) ? [$record] : []));
            $result = Components::evaluator()->evaluate($this->formatter, $context, $column, sprintf('formatStateUsing() of column "%s"', $column->columnName()));
        } elseif ($this->formatter instanceof Closure) {
            $result = ($this->formatter)($value, $record);
        } else {
            $formatter = app($this->formatter);
            $result = $formatter instanceof ColumnFormatter ? $formatter->format($value, $record, $column) : null;
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
