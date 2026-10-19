<?php

declare(strict_types=1);

namespace Gcego\Analytics\Filters;

use Gcego\Analytics\Exceptions\AnalyticsQueryException;
use Gcego\Analytics\Reports\ReportFilter;

/**
 * Turns untrusted filter input (query strings, Livewire state) into
 * AnalyticsFilter values, only for declared ReportFilters: unknown keys are
 * refused, empty values ignored, lists bounded. The planner then checks
 * each value against the dataset schema (types, options, permissions).
 */
final class FilterResolver
{
    /**
     * @param  list<ReportFilter>  $declared
     * @param  array<array-key, mixed>  $input
     * @return list<AnalyticsFilter>
     */
    public static function resolve(array $declared, array $input, bool $applyDefaults = true): array
    {
        $byName = [];

        foreach ($declared as $filter) {
            $byName[$filter->nodeName()] = $filter;
        }

        foreach (array_keys($input) as $key) {
            if (! is_string($key) || ! isset($byName[$key])) {
                throw AnalyticsQueryException::rejected('filter', 'Unknown filter.');
            }
        }

        $filters = [];

        foreach ($byName as $name => $filter) {
            $value = array_key_exists($name, $input) ? $input[$name] : ($applyDefaults ? $filter->default() : null);

            if ($value === null || $value === '' || $value === []) {
                if ($filter->isRequired()) {
                    throw AnalyticsQueryException::rejected('filter', sprintf('Choose a value for "%s".', $filter->label()));
                }

                continue;
            }

            if ($filter->isMultiple()) {
                $values = array_values(array_filter(is_array($value) ? $value : [$value], static fn (mixed $entry): bool => is_scalar($entry) && $entry !== ''));

                if ($values === []) {
                    continue;
                }

                if (count($values) > 100) {
                    throw AnalyticsQueryException::rejected('filter', 'Too many values.');
                }

                $filters[] = new AnalyticsFilter($name, FilterOperator::In, array_map(static fn (mixed $entry): string|int|float|bool => is_scalar($entry) ? $entry : '', $values));

                continue;
            }

            if (! is_scalar($value)) {
                throw AnalyticsQueryException::rejected('filter', sprintf('"%s" takes one value.', $filter->label()));
            }

            $filters[] = new AnalyticsFilter($name, FilterOperator::from($filter->operator()), $value);
        }

        return $filters;
    }

    /**
     * A period from untrusted input: a preset, or custom dates.
     */
    public static function range(?string $preset, ?string $from, ?string $to, string $timezone, ?DateRangePreset $default): ?DateRange
    {
        if ($preset === null || $preset === '') {
            return $default === null ? null : DateRange::preset($default, $timezone);
        }

        if ($preset === DateRangePreset::Custom->value) {
            return DateRange::between((string) $from, (string) $to, $timezone);
        }

        $known = DateRangePreset::tryFrom($preset) ?? throw AnalyticsQueryException::rejected('date_range', 'Unknown period.');

        return DateRange::preset($known, $timezone);
    }
}
