<?php

declare(strict_types=1);

namespace Gcego\Analytics\Dashboards;

use Gcego\Analytics\Comparisons\Comparison;
use Gcego\Analytics\Comparisons\ComparisonMode;
use Gcego\Analytics\Dashboard;
use Gcego\Analytics\Exceptions\AnalyticsQueryException;
use Gcego\Analytics\Filters\AnalyticsFilter;
use Gcego\Analytics\Filters\DateRange;
use Gcego\Analytics\Filters\FilterResolver;

/**
 * The validated selection of a dashboard: period, comparison and shared
 * filters. Built from untrusted input (Livewire state, query strings) by
 * fromInput(); never holds query definitions, only values the dashboard declares.
 */
final readonly class DashboardState
{
    /**
     * @param  list<AnalyticsFilter>  $filters
     * @param  array<string, mixed>  $values  The accepted raw filter values (for the UI).
     */
    public function __construct(public DateRange $range, public ?Comparison $comparison, public array $filters, public array $values) {}

    /**
     * @param  array<array-key, mixed>  $filters
     */
    public static function fromInput(Dashboard $dashboard, string $timezone, ?string $range = null, ?string $from = null, ?string $to = null, ?string $comparison = null, array $filters = []): self
    {
        $period = FilterResolver::range($range, $from, $to, $timezone, $dashboard->defaultRange()) ?? DateRange::preset($dashboard->defaultRange(), $timezone);
        $mode = $comparison === null || $comparison === '' ? $dashboard->comparison() : (ComparisonMode::tryFrom($comparison) ?? throw AnalyticsQueryException::rejected('comparison', 'Unknown comparison.'));

        if ($mode === ComparisonMode::Custom) {
            throw AnalyticsQueryException::rejected('comparison', 'Unknown comparison.');
        }

        $values = [];

        foreach ($filters as $name => $value) {
            if (is_string($name) && $value !== null && $value !== '' && $value !== []) {
                $values[$name] = $value;
            }
        }

        return new self($period, $mode === ComparisonMode::None ? null : new Comparison($mode), FilterResolver::resolve($dashboard->filters(), $values, false), $values);
    }

    public function key(): string
    {
        return $this->range->key().'|'.($this->comparison?->key() ?? '-').'|'.implode(',', array_map(static fn (AnalyticsFilter $filter): string => $filter->key(), $this->filters));
    }
}
