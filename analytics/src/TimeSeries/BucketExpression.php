<?php

declare(strict_types=1);

namespace Gcego\Analytics\TimeSeries;

use Gcego\Analytics\Exceptions\AnalyticsQueryException;

/**
 * Database expressions grouping a wrapped date column into TimeInterval
 * keys, shifted from the database time zone to the report's:
 *
 * - PostgreSQL converts exactly (AT TIME ZONE, daylight saving included);
 * - MySQL, MariaDB and SQLite shift by the zone's offset at the start of
 *   the period (a daylight-saving change inside the period moves the
 *   affected hour's bucket boundary by that hour).
 *
 * Built only from a column the planner validated and fixed formats; the
 * offset is an integer and the zone names come from PHP's identifier list.
 */
final class BucketExpression
{
    public static function sql(string $driver, string $column, TimeInterval $interval, int $offsetSeconds, string $databaseTimezone, string $timezone): string
    {
        return match ($driver) {
            'sqlite' => self::sqlite(sprintf("datetime(%s, '%+d seconds')", $column, $offsetSeconds), $interval),
            'mysql', 'mariadb' => self::mysql(sprintf('DATE_ADD(%s, INTERVAL %d SECOND)', $column, $offsetSeconds), $interval),
            'pgsql' => self::pgsql(sprintf("((%s) AT TIME ZONE '%s') AT TIME ZONE '%s'", $column, self::zone($databaseTimezone), self::zone($timezone)), $interval),
            default => throw AnalyticsQueryException::rejected('driver', sprintf('Time series are not supported on the "%s" database driver.', $driver)),
        };
    }

    private static function sqlite(string $moment, TimeInterval $interval): string
    {
        return match ($interval) {
            TimeInterval::Hour => sprintf("strftime('%%Y-%%m-%%d %%H:00', %s)", $moment),
            TimeInterval::Day => sprintf("strftime('%%Y-%%m-%%d', %s)", $moment),
            TimeInterval::Week => sprintf("date(%1\$s, '-' || ((CAST(strftime('%%w', %1\$s) AS INTEGER) + 6) %% 7) || ' days')", $moment),
            TimeInterval::Month => sprintf("strftime('%%Y-%%m', %s)", $moment),
            TimeInterval::Quarter => sprintf("strftime('%%Y', %1\$s) || '-Q' || ((CAST(strftime('%%m', %1\$s) AS INTEGER) + 2) / 3)", $moment),
            TimeInterval::Year => sprintf("strftime('%%Y', %s)", $moment),
        };
    }

    private static function mysql(string $moment, TimeInterval $interval): string
    {
        return match ($interval) {
            TimeInterval::Hour => sprintf("DATE_FORMAT(%s, '%%Y-%%m-%%d %%H:00')", $moment),
            TimeInterval::Day => sprintf("DATE_FORMAT(%s, '%%Y-%%m-%%d')", $moment),
            TimeInterval::Week => sprintf("DATE_FORMAT(DATE_SUB(%1\$s, INTERVAL WEEKDAY(%1\$s) DAY), '%%Y-%%m-%%d')", $moment),
            TimeInterval::Month => sprintf("DATE_FORMAT(%s, '%%Y-%%m')", $moment),
            TimeInterval::Quarter => sprintf("CONCAT(YEAR(%1\$s), '-Q', QUARTER(%1\$s))", $moment),
            TimeInterval::Year => sprintf("DATE_FORMAT(%s, '%%Y')", $moment),
        };
    }

    private static function pgsql(string $moment, TimeInterval $interval): string
    {
        return match ($interval) {
            TimeInterval::Hour => sprintf("to_char(%s, 'YYYY-MM-DD HH24:00')", $moment),
            TimeInterval::Day => sprintf("to_char(%s, 'YYYY-MM-DD')", $moment),
            TimeInterval::Week => sprintf("to_char(date_trunc('week', %s), 'YYYY-MM-DD')", $moment),
            TimeInterval::Month => sprintf("to_char(%s, 'YYYY-MM')", $moment),
            TimeInterval::Quarter => sprintf("to_char(%s, 'YYYY-\"Q\"Q')", $moment),
            TimeInterval::Year => sprintf("to_char(%s, 'YYYY')", $moment),
        };
    }

    private static function zone(string $timezone): string
    {
        return in_array($timezone, timezone_identifiers_list(), true) || $timezone === 'UTC' ? $timezone : throw AnalyticsQueryException::rejected('timezone', 'Unknown time zone.');
    }
}
