<?php

declare(strict_types=1);

namespace Gcego\Testing\Performance;

use Closure;
use Gcego\Testing\Assertions\Verifier;
use Gcego\Testing\Testables\Testable;

/**
 * Assertions about one measured callback.
 *
 *   GcegoTest::performance()->measure(fn () => $report->run())
 *       ->assertQueryCountAtMost(10)
 *       ->assertNoRepeatedQueries();
 *
 * Query counts are exact and deterministic; durations and memory are not,
 * so only assert generous limits on them.
 */
final class PerformanceTestable extends Testable
{
    private ?Measurement $measurement = null;

    public function __construct(Verifier $verifier, private readonly PerformanceRecorder $recorder, private readonly int $repeatedThreshold = 5)
    {
        parent::__construct($verifier);
    }

    /**
     * @param  Closure(): mixed  $callback
     */
    public function measure(Closure $callback): self
    {
        $this->measurement = $this->recorder->measure($callback);

        return $this;
    }

    public function measurement(): Measurement
    {
        return $this->measurement ?? throw new \LogicException('Call measure() first.');
    }

    public function result(): mixed
    {
        return $this->measurement()->result;
    }

    public function assertQueryCount(int $count): self
    {
        $actual = $this->measurement()->queryCount();

        return $this->check($actual === $count, sprintf('assertQueryCount(%d)', $count), sprintf('Expected exactly %d queries.', $count), ['queries' => $count], ['queries' => $actual, 'sql' => $this->measurement()->sql()]);
    }

    public function assertQueryCountAtMost(int $limit): self
    {
        $actual = $this->measurement()->queryCount();

        return $this->check($actual <= $limit, sprintf('assertQueryCountAtMost(%d)', $limit), sprintf('Expected at most %d queries.', $limit), ['queries' => '≤ '.$limit], ['queries' => $actual, 'sql' => $this->measurement()->sql()], 'Eager-load relations (With/Includable on the Source) or cache repeated lookups.');
    }

    public function assertNoQueries(): self
    {
        return $this->assertQueryCount(0);
    }

    /**
     * No identical query (same SQL and bindings) runs twice.
     */
    public function assertNoDuplicateQueries(): self
    {
        $duplicates = $this->measurement()->duplicates();

        return $this->check($duplicates === [], 'assertNoDuplicateQueries()', 'Expected no query to run twice with the same bindings.', ['duplicates' => []], ['duplicates' => $duplicates]);
    }

    /**
     * No query shape runs $threshold or more times (the N+1 heuristic).
     */
    public function assertNoRepeatedQueries(?int $threshold = null): self
    {
        $threshold ??= $this->repeatedThreshold;
        $repeated = $this->measurement()->repeatedShapes($threshold);

        return $this->check($repeated === [], sprintf('assertNoRepeatedQueries(%d)', $threshold), sprintf('Expected no query shape to run %d or more times (a likely N+1).', $threshold), ['repeated' => []], ['repeated' => $repeated], 'Load the relation for all rows at once (eager loading) instead of per row.');
    }

    public function assertMemoryBelow(int $bytes): self
    {
        $actual = $this->measurement()->memoryBytes;

        return $this->check($actual < $bytes, sprintf('assertMemoryBelow(%d)', $bytes), sprintf('Expected the callback to retain less than %s.', $this->bytes($bytes)), ['memory' => '< '.$this->bytes($bytes)], ['memory' => $this->bytes($actual), 'peak growth' => $this->bytes($this->measurement()->peakMemoryBytes)]);
    }

    /**
     * Wall-clock time. CI machines vary: use limits several times larger
     * than what you observe locally.
     */
    public function assertDurationBelow(float $milliseconds): self
    {
        $actual = $this->measurement()->durationMs;

        return $this->check($actual < $milliseconds, sprintf('assertDurationBelow(%s)', $milliseconds), sprintf('Expected the callback to take less than %s ms.', $milliseconds), ['duration' => '< '.$milliseconds.' ms'], ['duration' => round($actual, 1).' ms']);
    }

    public function assertPayloadBelow(int $bytes): self
    {
        $actual = $this->measurement()->payloadBytes();

        return $this->check($actual < $bytes, sprintf('assertPayloadBelow(%d)', $bytes), sprintf('Expected the result to be smaller than %s.', $this->bytes($bytes)), ['size' => '< '.$this->bytes($bytes)], ['size' => $this->bytes($actual)]);
    }

    /**
     * A second run of the callback runs no more than $queries queries: what
     * a cache hit should look like.
     *
     * @param  Closure(): mixed  $callback
     */
    public function assertCachedAfterFirstRun(Closure $callback, int $queries = 0): self
    {
        $this->recorder->measure($callback);
        $second = $this->recorder->measure($callback);

        return $this->check($second->queryCount() <= $queries, sprintf('assertCachedAfterFirstRun(%d)', $queries), 'Expected the second run to be served from the cache.', ['queries on second run' => '≤ '.$queries], ['queries on second run' => $second->queryCount(), 'sql' => $second->sql()]);
    }

    protected function subject(): string
    {
        return 'performance';
    }

    protected function context(): array
    {
        $measurement = $this->measurement;

        return $measurement === null ? [] : ['Queries' => $measurement->queryCount(), 'Duration' => round($measurement->durationMs, 1).' ms'];
    }

    private function bytes(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1).' MB' : ($bytes >= 1024 ? round($bytes / 1024, 1).' KB' : $bytes.' B');
    }
}
