<?php

declare(strict_types=1);

namespace Gcego\Testing\Performance;

/**
 * What one measured callback did: its queries, how much memory it
 * allocated, how long it took and what it returned.
 */
final readonly class Measurement
{
    /**
     * @param  list<RecordedQuery>  $queries
     */
    public function __construct(public mixed $result, public array $queries, public float $durationMs, public int $memoryBytes, public int $peakMemoryBytes) {}

    public function queryCount(): int
    {
        return count($this->queries);
    }

    /**
     * Identical queries (same SQL and bindings) run more than once.
     *
     * @return array<string, int> "SQL [bindings]" => executions
     */
    public function duplicates(): array
    {
        $counts = [];
        $labels = [];

        foreach ($this->queries as $query) {
            $identity = $query->identity();
            $counts[$identity] = ($counts[$identity] ?? 0) + 1;
            $labels[$identity] = $query->label();
        }

        $duplicates = [];

        foreach ($counts as $identity => $count) {
            if ($count > 1) {
                $duplicates[$labels[$identity]] = $count;
            }
        }

        return $duplicates;
    }

    /**
     * Query shapes executed at least $threshold times: the usual sign of
     * an N+1 (one query per row). It is a heuristic, not a proof: a
     * deliberate loop looks the same.
     *
     * @return array<string, int> shape => executions
     */
    public function repeatedShapes(int $threshold): array
    {
        $counts = [];

        foreach ($this->queries as $query) {
            $shape = $query->shape();
            $counts[$shape] = ($counts[$shape] ?? 0) + 1;
        }

        return array_filter($counts, static fn (int $count): bool => $count >= $threshold);
    }

    /**
     * The size of the result as JSON (or its length, for a string).
     */
    public function payloadBytes(): int
    {
        if (is_string($this->result)) {
            return strlen($this->result);
        }

        if ($this->result instanceof \Stringable) {
            return strlen((string) $this->result);
        }

        $json = json_encode($this->result, JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $json === false ? 0 : strlen($json);
    }

    /**
     * @return list<string>
     */
    public function sql(): array
    {
        return array_map(static fn (RecordedQuery $query): string => $query->sql, $this->queries);
    }
}
