<?php

declare(strict_types=1);

namespace Gcego\Analytics\Comparisons;

enum ComparisonMode: string
{
    case None = 'none';
    case PreviousPeriod = 'previous_period';
    case PreviousYear = 'previous_year';
    case Custom = 'custom';
}
