<?php

declare(strict_types=1);

namespace Gcego\Testing\Performance;

use Closure;

/**
 * Runs a callback and records its queries, wall time and memory.
 *
 * Memory is the growth of allocated memory across the call (PHP may reuse
 * freed memory, so treat it as an upper bound check, not a profile).
 */
final readonly class PerformanceRecorder
{
    public function __construct(private QueryCounter $queries) {}

    /**
     * @param  Closure(): mixed  $callback
     */
    public function measure(Closure $callback): Measurement
    {
        gc_collect_cycles();
        $memory = memory_get_usage();
        $peak = memory_get_peak_usage();

        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }

        $started = hrtime(true);
        [$result, $queries] = $this->queries->record($callback);
        $duration = (hrtime(true) - $started) / 1_000_000;

        return new Measurement($result, $queries, $duration, max(0, memory_get_usage() - $memory), max(0, memory_get_peak_usage() - (function_exists('memory_reset_peak_usage') ? $memory : $peak)));
    }
}
