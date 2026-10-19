<?php

declare(strict_types=1);

namespace Gcego\Analytics\TimeSeries;

/**
 * Buckets without observations: zero (counts and sums: nothing happened),
 * null (averages and extremes: no value, shown as a gap), or none (keep only observed buckets).
 */
enum FillStrategy: string
{
    case Zero = 'zero';
    case Null = 'null';
    case None = 'none';
}
