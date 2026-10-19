<?php

declare(strict_types=1);

namespace Gcego\Analytics;

use Gcego\Analytics\Comparisons\ComparisonMode;
use Gcego\Analytics\Dashboards\Chart;
use Gcego\Analytics\Dashboards\Kpi;
use Gcego\Analytics\Dashboards\ReportSection;
use Gcego\Analytics\Definitions\AnalyticsNode;
use Gcego\Analytics\Filters\DateRangePreset;
use Gcego\Analytics\Metrics\Metric;
use Gcego\Analytics\Reports\ReportFilter;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;

/**
 * An analytics dashboard: KPI cards, charts and report sections sharing a
 * period, a comparison and filters.
 *
 *     return new Dashboard('academic-overview',
 *         new Ability('analytics.view'),
 *         new Metric(name: 'total_students', label: 'Total Students', model: Student::class, aggregate: 'count'),
 *         new Kpi('pass_rate'),
 *         new Chart('registrations', 'Registrations', metric: 'daily_registrations', interval: 'day', compare: true),
 *         new ReportSection('student-performance'),
 *         new ReportFilter('level', 'Level'),
 *         title: 'Academic Overview',
 *     );
 *
 * Inline Metric children belong to the dashboard (calculated only through
 * it) and, without requirements of their own, inherit the dashboard's;
 * Kpi and Chart may name registered metrics too. Filters apply to
 * every metric whose dataset has that dimension or field. Each part is
 * authorized on its own: a part the viewer may not see is left out.
 * Registry kind "analytics-dashboard".
 */
final class Dashboard extends AnalyticsNode
{
    public const string Title = 'title';

    public const string DefaultRange = 'default_range';

    /** none, previous_period or previous_year. */
    public const string Comparison = 'comparison';

    /** Seconds between automatic refreshes (at least 60), or none. */
    public const string Refresh = 'refresh';

    public function title(): string
    {
        return $this->text(self::Title) ?? $this->label();
    }

    public function defaultRange(): DateRangePreset
    {
        return DateRangePreset::tryFrom($this->text(self::DefaultRange) ?? '') ?? DateRangePreset::Last30Days;
    }

    public function comparison(): ComparisonMode
    {
        return ComparisonMode::tryFrom($this->text(self::Comparison) ?? '') ?? ComparisonMode::PreviousPeriod;
    }

    public function refresh(): ?int
    {
        return $this->integer(self::Refresh);
    }

    /**
     * @return list<Metric>
     */
    public function metrics(): array
    {
        // Inline metrics without requirements of their own are protected by the dashboard's.
        return array_map(fn (Metric $metric): Metric => $metric->requirements() === [] && $this->requirements() !== [] ? new Metric(...[...array_values($metric->properties()->all()), ...$this->requirements()]) : $metric, $this->childrenOf(Metric::class));
    }

    public function metric(string $name): ?Metric
    {
        foreach ($this->metrics() as $metric) {
            if ($metric->nodeName() === $name) {
                return $metric;
            }
        }

        return null;
    }

    /**
     * KPI cards in order: inline metrics and Kpi references.
     *
     * @return list<string>
     */
    public function kpis(): array
    {
        $names = [];

        foreach ($this->children() as $child) {
            if ($child instanceof Metric) {
                $names[] = $child->nodeName();
            } elseif ($child instanceof Kpi) {
                $names[] = $child->metric();
            }
        }

        return $names;
    }

    /**
     * @return list<Chart>
     */
    public function charts(): array
    {
        return $this->childrenOf(Chart::class);
    }

    /**
     * @return list<ReportSection>
     */
    public function reports(): array
    {
        return $this->childrenOf(ReportSection::class);
    }

    /**
     * @return list<ReportFilter>
     */
    public function filters(): array
    {
        return $this->childrenOf(ReportFilter::class);
    }

    /**
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];

        foreach ([array_map(static fn (Metric $metric): string => $metric->nodeName(), $this->metrics()), array_map(static fn (Chart $chart): string => $chart->nodeName(), $this->charts()), array_map(static fn (ReportFilter $filter): string => $filter->nodeName(), $this->filters())] as $names) {
            foreach (array_count_values($names) as $name => $count) {
                if ($count > 1) {
                    $problems[] = sprintf('"%s" is declared more than once.', $name);
                }
            }
        }

        if ($this->kpis() === [] && $this->charts() === [] && $this->reports() === []) {
            $problems[] = 'a dashboard needs at least one metric, KPI, chart or report.';
        }

        return $problems;
    }

    protected static function namedArguments(): array
    {
        return [...parent::namedArguments(), 'title' => self::Title, 'defaultRange' => self::DefaultRange, 'comparison' => self::Comparison, 'refresh' => self::Refresh];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('analytics.dashboard')
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: self::validName(...), ruleDescription: 'a lowercase name such as "academic-overview"'),
                new PropertyDefinition(self::Title, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && trim($value) !== '' && mb_strlen($value) <= 150, ruleDescription: 'at most 150 characters'),
                new PropertyDefinition(self::DefaultRange, Type::string(), allowedValues: array_values(array_diff(DateRangePreset::values(), ['custom']))),
                new PropertyDefinition(self::Comparison, Type::string(), allowedValues: ['none', 'previous_period', 'previous_year']),
                new PropertyDefinition(self::Refresh, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 60 && $value <= 86400, ruleDescription: 'between 60 and 86400 seconds'),
            )
            ->withAllowedChildren([Metric::class, Kpi::class, Chart::class, ReportSection::class, ReportFilter::class])
            ->withConstraint(static fn (ComponentInterface $dashboard): array => $dashboard instanceof self ? $dashboard->problems() : []);
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Title];
    }
}
