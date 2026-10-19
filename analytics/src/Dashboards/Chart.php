<?php

declare(strict_types=1);

namespace Gcego\Analytics\Dashboards;

use Gcego\Analytics\Definitions\AnalyticsNode;
use Gcego\Analytics\TimeSeries\TimeInterval;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;

/**
 * A chart of a metric, over time or by a dimension (rendered by gcego/widgets' Chart.js adapter):
 *
 *     new Chart('registrations', 'Registrations', metric: 'daily_registrations', interval: 'day', compare: true)
 *     new Chart('by-subject', 'Average score by subject', metric: 'average_score', groupBy: 'subject_id', type: 'bar', limit: 10)
 *
 * Every chart also renders as a data table (never chart-only information).
 */
final class Chart extends AnalyticsNode
{
    public const string Metric = 'metric';

    /** line, bar, area, pie or doughnut. */
    public const string ChartType = 'chart_type';

    public const string Interval = 'interval';

    public const string GroupBy = 'group_by';

    /** Overlay the comparison period (time series). */
    public const string Compare = 'compare';

    /** Top N groups; the rest become "Other" (counts and sums). */
    public const string Limit = 'limit';

    public const array Types = ['line', 'bar', 'area', 'pie', 'doughnut'];

    public function metric(): string
    {
        return $this->text(self::Metric) ?? '';
    }

    public function chartType(): string
    {
        return $this->text(self::ChartType) ?? ($this->groupBy() === null ? 'line' : 'bar');
    }

    public function interval(): ?TimeInterval
    {
        return TimeInterval::tryFrom($this->text(self::Interval) ?? '');
    }

    public function groupBy(): ?string
    {
        return $this->text(self::GroupBy);
    }

    public function compares(): bool
    {
        return $this->get(self::Compare) === true;
    }

    public function limit(): int
    {
        return $this->integer(self::Limit) ?? 12;
    }

    protected static function namedArguments(): array
    {
        return [...parent::namedArguments(), 'metric' => self::Metric, 'type' => self::ChartType, 'interval' => self::Interval, 'groupBy' => self::GroupBy, 'compare' => self::Compare, 'limit' => self::Limit];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('analytics.chart')
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: self::validName(...), ruleDescription: 'a lowercase name'),
                new PropertyDefinition(self::Metric, Type::string(), required: true, rule: self::validName(...), ruleDescription: 'a metric name'),
                new PropertyDefinition(self::ChartType, Type::string(), allowedValues: self::Types),
                new PropertyDefinition(self::Interval, Type::string(), allowedValues: TimeInterval::values()),
                new PropertyDefinition(self::GroupBy, Type::string(), rule: self::validColumn(...), ruleDescription: 'a dimension'),
                new PropertyDefinition(self::Compare, Type::bool(), default: false),
                new PropertyDefinition(self::Limit, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 1 && $value <= 50, ruleDescription: 'between 1 and 50'),
            )
            ->withoutChildren()
            ->withConstraint(static fn (ComponentInterface $chart): array => $chart instanceof self && ($chart->interval() === null) === ($chart->groupBy() === null) ? ['a chart needs exactly one of Interval (over time) or GroupBy (a dimension).'] : []);
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }
}
