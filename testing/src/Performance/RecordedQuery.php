<?php

declare(strict_types=1);

namespace Gcego\Testing\Performance;

/**
 * One executed query. shape() strips literal values so the same query
 * run with different keys is recognised as a repetition.
 */
final readonly class RecordedQuery
{
    /**
     * @param  array<array-key, mixed>  $bindings
     */
    public function __construct(public string $sql, public array $bindings, public float $time, public string $connection) {}

    public function shape(): string
    {
        $sql = (string) preg_replace("/'(?:[^'\\\\]|\\\\.)*'/", '?', $this->sql);
        $sql = (string) preg_replace('/\b\d+(?:\.\d+)?\b/', '?', $sql);
        $sql = (string) preg_replace('/\(\s*\?(?:\s*,\s*\?)*\s*\)/', '(…)', $sql);

        return trim((string) preg_replace('/\s+/', ' ', $sql));
    }

    /**
     * "SQL [bindings]" for failure output. Binding values are test data;
     * failure output redacts sensitive keys, not values, so keep secrets
     * out of query bindings in tests.
     */
    public function label(): string
    {
        $bindings = json_encode(array_values($this->bindings), JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $this->sql.' '.($bindings === false ? '[…]' : $bindings);
    }

    /**
     * The SQL with its bindings, for duplicate detection.
     */
    public function identity(): string
    {
        return $this->connection.'|'.$this->sql.'|'.serialize($this->bindings);
    }
}
