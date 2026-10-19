<?php

declare(strict_types=1);

namespace Gcego\Testing\Performance;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;

/**
 * Records the queries executed while a callback runs, through Laravel's
 * QueryExecuted event. Only that callback's queries are recorded; the
 * listener stops recording as soon as it returns, even when it throws.
 */
final readonly class QueryCounter
{
    public function __construct(private Dispatcher $events) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return array{T, list<RecordedQuery>}
     */
    public function record(Closure $callback): array
    {
        $recorder = new QueryRecording;

        $this->events->listen(QueryExecuted::class, static function (QueryExecuted $query) use ($recorder): void {
            $recorder->add(new RecordedQuery($query->sql, $query->bindings, $query->time, $query->connectionName));
        });

        try {
            $result = $callback();
        } finally {
            $recorder->stop();
        }

        return [$result, $recorder->queries()];
    }

    /**
     * @param  Closure(): mixed  $callback
     */
    public function count(Closure $callback): int
    {
        return count($this->record($callback)[1]);
    }
}
