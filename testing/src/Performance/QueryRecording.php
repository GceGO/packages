<?php

declare(strict_types=1);

namespace Gcego\Testing\Performance;

/**
 * The queries one QueryCounter::record() call collects. The listener it
 * feeds stays registered for the application's lifetime, so stop() makes
 * every later query a no-op for it.
 */
final class QueryRecording
{
    /** @var list<RecordedQuery> */
    private array $queries = [];

    private bool $recording = true;

    public function add(RecordedQuery $query): void
    {
        if ($this->recording) {
            $this->queries[] = $query;
        }
    }

    public function stop(): void
    {
        $this->recording = false;
    }

    /**
     * @return list<RecordedQuery>
     */
    public function queries(): array
    {
        return $this->queries;
    }
}
