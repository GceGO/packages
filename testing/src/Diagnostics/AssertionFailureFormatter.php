<?php

declare(strict_types=1);

namespace Gcego\Testing\Diagnostics;

use BackedEnum;
use Closure;
use JsonException;
use Stringable;
use UnitEnum;

/**
 * Turns a Failure into the text PHPUnit prints:
 *
 *   GCEGO TABLE ASSERTION FAILED
 *
 *   Expected table column [name] to be searchable.
 *
 *   Table: subjects
 *   Assertion: assertColumnSearchable('name')
 *
 *   Expected:
 *     searchable = true
 *   Actual:
 *     searchable = false
 *
 *   Suggestion:
 *     ...
 */
final readonly class AssertionFailureFormatter
{
    public function __construct(private Redactor $redactor = new Redactor, private bool $verbose = true) {}

    public function format(Failure $failure): string
    {
        $lines = ['GCEGO '.strtoupper($failure->subject).' ASSERTION FAILED', '', $failure->message];

        if (! $this->verbose) {
            return implode("\n", $lines);
        }

        $context = $this->redactor->redact($failure->context);
        $lines[] = '';

        foreach ($context as $key => $value) {
            $lines[] = $key.': '.$this->scalar($value);
        }

        $lines[] = 'Assertion: '.$failure->assertion;

        foreach (['Expected' => $failure->expected, 'Actual' => $failure->actual] as $heading => $values) {
            if ($values === []) {
                continue;
            }

            $lines[] = '';
            $lines[] = $heading.':';

            foreach ($this->redactor->redact($values) as $key => $value) {
                if (is_array($value) && $value !== [] && count($value) <= 50 && array_filter($value, static fn (mixed $item): bool => is_scalar($item) || $item === null) === $value) {
                    $lines[] = '  '.$key.':';

                    foreach ($value as $index => $item) {
                        $lines[] = '    - '.(array_is_list($value) ? '' : $index.' → ').$this->scalar($item);
                    }

                    continue;
                }

                $lines[] = '  '.$key.' = '.$this->scalar($value);
            }
        }

        if ($failure->suggestion !== null) {
            $lines[] = '';
            $lines[] = 'Suggestion:';
            $lines[] = '  '.$failure->suggestion;
        }

        return implode("\n", $lines);
    }

    /**
     * A short, single-line export of a value.
     */
    public function scalar(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            is_string($value) => $this->limit($value),
            $value instanceof BackedEnum => $value::class.'::'.$value->name.' ('.$value->value.')',
            $value instanceof UnitEnum => $value::class.'::'.$value->name,
            $value instanceof Closure => 'Closure',
            $value instanceof Stringable => $this->limit((string) $value),
            is_array($value) => $this->json($value),
            is_object($value) => $value::class,
            default => get_debug_type($value),
        };
    }

    /**
     * @param  array<array-key, mixed>  $value
     */
    private function json(array $value): string
    {
        try {
            return $this->limit(json_encode($this->redactor->redact($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
        } catch (JsonException) {
            return 'array('.count($value).')';
        }
    }

    private function limit(string $value): string
    {
        $value = (string) preg_replace('/\s+/', ' ', $value);

        return mb_strlen($value) > 500 ? mb_substr($value, 0, 500).'…' : $value;
    }
}
