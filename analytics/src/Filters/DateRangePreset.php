<?php

declare(strict_types=1);

namespace Gcego\Analytics\Filters;

enum DateRangePreset: string
{
    case Today = 'today';
    case Yesterday = 'yesterday';
    case Last7Days = 'last_7_days';
    case Last30Days = 'last_30_days';
    case Last90Days = 'last_90_days';
    case CurrentMonth = 'current_month';
    case PreviousMonth = 'previous_month';
    case CurrentYear = 'current_year';
    case PreviousYear = 'previous_year';
    case Custom = 'custom';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
