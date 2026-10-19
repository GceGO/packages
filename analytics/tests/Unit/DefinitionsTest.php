<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Gcego\Analytics\AnalyticsConfig;
use Gcego\Analytics\Comparisons\ComparisonMode;
use Gcego\Analytics\Comparisons\ComparisonResult;
use Gcego\Analytics\Dashboard;
use Gcego\Analytics\Dashboards\Chart;
use Gcego\Analytics\Dashboards\Kpi;
use Gcego\Analytics\Datasets\Dataset;
use Gcego\Analytics\Datasets\Field;
use Gcego\Analytics\Datasets\FieldType;
use Gcego\Analytics\Dimensions\Dimension;
use Gcego\Analytics\Exceptions\AnalyticsQueryException;
use Gcego\Analytics\Exceptions\InvalidDefinitionException;
use Gcego\Analytics\Exports\ExportWriter;
use Gcego\Analytics\Filters\DateRange;
use Gcego\Analytics\Integration\Workflows\WorkflowTasksProvider;
use Gcego\Analytics\Measures\Aggregate;
use Gcego\Analytics\Metrics\Formula;
use Gcego\Analytics\Metrics\Metric;
use Gcego\Analytics\Metrics\MetricKind;
use Gcego\Analytics\Reports\Report;
use Gcego\Analytics\Reports\ReportColumn;
use Gcego\Analytics\Tests\Fixtures\Models\Subject;
use Gcego\Analytics\TimeSeries\FillStrategy;
use Gcego\Analytics\TimeSeries\TimeInterval;
use Gcego\Components\Component;
use Gcego\Components\Property;

/**
 * @return list<string>
 */
function problemsOf(Component $definition): array
{
    return array_map(static fn ($error): string => $error->format(), $definition->validate()->errors());
}

it('normalizes shorthand, named and Property syntax to one definition', function (): void {
    $shorthand = new Metric('total_students', Subject::class);
    $named = new Metric(name: 'total_students', model: Subject::class, aggregate: 'count');
    $properties = new Metric(new Property(Metric::Name, 'total_students'), new Property(Metric::Model, Subject::class), new Property(Metric::Aggregate, 'count'));

    expect($shorthand->kind())->toBe(MetricKind::Count)->and($named->kind())->toBe(MetricKind::Count)
        ->and($named->model())->toBe(Subject::class)->and($properties->model())->toBe(Subject::class)
        ->and(problemsOf($shorthand))->toBe([])->and(problemsOf($named))->toBe([])->and(problemsOf($properties))->toBe([])
        ->and($named->label())->toBe('Total students');
});

it('refuses unknown named arguments', function (): void {
    new Metric(name: 'x', agregate: 'sum');
})->throws(InvalidDefinitionException::class, 'it has no "agregate" argument');

it('validates metrics', function (Closure $make, string $problem): void {
    expect(implode("\n", problemsOf($make())))->toContain($problem);
})->with([
    'unsupported aggregate' => [fn () => new Metric(name: 'm', model: Subject::class, aggregate: 'median'), 'median'],
    'no source' => [fn () => new Metric(name: 'm'), 'exactly one of Dataset'],
    'field missing' => [fn () => new Metric(name: 'm', model: Subject::class, aggregate: 'sum'), 'needs a Field'],
    'ratio parts' => [fn () => new Metric(name: 'm', aggregate: 'ratio', numerator: 'a'), 'Numerator and a Denominator'],
    'composite with model' => [fn () => new Metric(name: 'm', aggregate: 'percentage', numerator: 'a', denominator: 'b', model: Subject::class), 'takes no dataset'],
    'self reference' => [fn () => new Metric(name: 'm', aggregate: 'derived', formula: '{m} * 2'), 'from itself'],
    'unsafe formula' => [fn () => new Metric(name: 'm', aggregate: 'derived', formula: '{a}; system("ls")'), 'is not allowed'],
    'series without date' => [fn () => new Metric(name: 'm', model: Subject::class, interval: 'day'), 'needs a DateColumn'],
    'bad interval' => [fn () => new Metric(name: 'm', model: Subject::class, interval: 'fortnight'), 'fortnight'],
]);

it('parses and evaluates formulas safely', function (): void {
    $formula = Formula::parse('({a} + {b}) * 2 - -{c} / 4');

    expect($formula->references())->toBe(['a', 'b', 'c'])
        ->and($formula->evaluate(['a' => 1, 'b' => 2, 'c' => 8]))->toBe(8.0)
        ->and($formula->evaluate(['a' => 1, 'b' => null, 'c' => 8]))->toBeNull()
        ->and(Formula::parse('{a} / {b}')->evaluate(['a' => 3, 'b' => 0]))->toBeNull()
        ->and(Formula::parse('{a} / {b}')->evaluate(['a' => 1, 'b' => 4]))->toBe(0.25)
        ->and(fn () => Formula::parse('({a} + 1'))->toThrow(InvalidDefinitionException::class, 'parentheses')
        ->and(fn () => Formula::parse('{a} +'))->toThrow(InvalidDefinitionException::class, 'incomplete')
        ->and(fn () => Formula::parse('{a} * exec()'))->toThrow(InvalidDefinitionException::class, 'not allowed');
});

it('validates datasets, reports and dashboards', function (): void {
    $dataset = new Dataset('d', Subject::class, new Field('score'), new Dimension('score'), new Field('name', type: 'string'), provider: WorkflowTasksProvider::class, dateColumn: 'name');
    $report = new Report('r', new ReportColumn('subject_id'), sort: 'missing', schedulable: true, dataset: 'd');
    $dashboard = new Dashboard('x', new Chart('c', metric: 'm'), new Chart('c', metric: 'm', interval: 'day', groupBy: 'level'));

    expect(implode("\n", problemsOf($dataset)))->toContain('exactly one of Model')->toContain('"score" is declared more than once')->toContain('is declared as a string field')
        ->and(implode("\n", problemsOf($report)))->toContain('at least one aggregate column')->toContain('sort column "missing"')->toContain('must also be exportable')
        ->and(implode("\n", problemsOf($dashboard)))->toContain('"c" is declared more than once')->toContain('exactly one of Interval');
});

it('offers aggregates that fit each field type', function (): void {
    expect((new Field('score'))->aggregates())->toBe(Aggregate::cases())
        ->and(array_map(static fn (Aggregate $aggregate): string => $aggregate->value, (new Field('name', type: 'string'))->aggregates()))->toBe(['count', 'count_distinct'])
        ->and((new Field('time', type: 'integer', aggregates: ['sum', 'avg']))->allows(Aggregate::Maximum))->toBeFalse()
        ->and((new Field('when', type: 'datetime'))->fieldType())->toBe(FieldType::DateTime)
        ->and((new Metric(name: 'avg', model: Subject::class, aggregate: 'avg', field: 'x'))->fill())->toBe(FillStrategy::Null)
        ->and((new Metric(name: 'n', model: Subject::class))->fill())->toBe(FillStrategy::Zero);
});

it('builds periods with exact, time-zone aware boundaries', function (): void {
    $now = CarbonImmutable::parse('2026-03-15 23:30:00', 'UTC');      // already 2026-03-16 00:30 in Douala (UTC+1)
    $douala = DateRange::preset('today', 'Africa/Douala', $now);
    $month = DateRange::preset('previous_month', 'UTC', $now);
    $custom = DateRange::between('2026-01-01', '2026-01-31', 'UTC');

    expect($douala->toArray())->toMatchArray(['from' => '2026-03-16', 'to' => '2026-03-16'])
        ->and($douala->bindings('UTC'))->toBe(['2026-03-15 23:00:00', '2026-03-16 23:00:00'])
        ->and($month->toArray())->toMatchArray(['from' => '2026-02-01', 'to' => '2026-02-28'])
        ->and(DateRange::preset('last_7_days', 'UTC', $now)->days())->toBe(7)
        ->and($custom->previousPeriod()->toArray())->toMatchArray(['from' => '2025-12-01', 'to' => '2025-12-31'])
        ->and(DateRange::between('2026-03-10', '2026-03-12', 'UTC')->previousPeriod()->toArray())->toMatchArray(['from' => '2026-03-07', 'to' => '2026-03-09'])
        ->and($custom->previousYear()->toArray())->toMatchArray(['from' => '2025-01-01', 'to' => '2025-01-31'])
        ->and(fn () => DateRange::between('2026-02-30', '2026-03-01', 'UTC'))->toThrow(AnalyticsQueryException::class)
        ->and(fn () => DateRange::between('2026-03-02', '2026-03-01', 'UTC'))->toThrow(AnalyticsQueryException::class)
        ->and(fn () => DateRange::preset('forever', 'UTC'))->toThrow(AnalyticsQueryException::class);
});

it('generates interval keys and labels', function (TimeInterval $interval, string $from, string $to, array $keys): void {
    $start = CarbonImmutable::parse($from, 'UTC');
    $end = CarbonImmutable::parse($to, 'UTC');

    expect($interval->keys($start, $end, 100))->toBe($keys)->and($interval->count($start, $end))->toBe(count($keys));
})->with([
    'hours' => [TimeInterval::Hour, '2026-03-10 22:00', '2026-03-11 00:59', ['2026-03-10 22:00', '2026-03-10 23:00', '2026-03-11 00:00']],
    'days' => [TimeInterval::Day, '2026-02-27', '2026-03-01 23:59', ['2026-02-27', '2026-02-28', '2026-03-01']],
    'iso weeks (Mondays)' => [TimeInterval::Week, '2026-03-04', '2026-03-16', ['2026-03-02', '2026-03-09', '2026-03-16']],
    'months' => [TimeInterval::Month, '2026-01-31', '2026-03-01', ['2026-01', '2026-02', '2026-03']],
    'quarters' => [TimeInterval::Quarter, '2025-11-15', '2026-04-02', ['2025-Q4', '2026-Q1', '2026-Q2']],
    'years' => [TimeInterval::Year, '2024-06-01', '2026-01-01', ['2024', '2025', '2026']],
]);

it('labels buckets for people', function (): void {
    expect(TimeInterval::Quarter->label('2026-Q1', 'UTC'))->toBe('Q1 2026')
        ->and(TimeInterval::Month->label('2026-03', 'UTC'))->toBe('Mar 2026')
        ->and(TimeInterval::Day->label('2026-03-09', 'UTC'))->toBe('9 Mar 2026');
});

it('compares values with explicit unavailable states', function (): void {
    $range = DateRange::between('2026-01-01', '2026-01-31', 'UTC');

    expect(ComparisonResult::between(ComparisonMode::PreviousPeriod, $range, 12, 8)->toArray())->toMatchArray(['change' => 4, 'percent_change' => 50.0, 'direction' => 'up'])
        ->and(ComparisonResult::between(ComparisonMode::PreviousPeriod, $range, 3, 0)->percentChange)->toBeNull()
        ->and(ComparisonResult::between(ComparisonMode::PreviousPeriod, $range, 3, 0)->direction)->toBe('up')
        ->and(ComparisonResult::between(ComparisonMode::PreviousPeriod, $range, 5, 5)->direction)->toBe('flat')
        ->and(ComparisonResult::between(ComparisonMode::PreviousPeriod, $range, null, 5)->direction)->toBe('unknown')
        ->and(ComparisonResult::between(ComparisonMode::PreviousYear, $range, 2.5, 5.0)->toArray())->toMatchArray(['change' => -2.5, 'percent_change' => -50.0, 'direction' => 'down']);
});

it('neutralizes spreadsheet formulas in exported text', function (): void {
    expect(ExportWriter::safe('=HYPERLINK("http://evil")'))->toBe('\'=HYPERLINK("http://evil")')
        ->and(ExportWriter::safe('+1'))->toBe("'+1")->and(ExportWriter::safe('-2+3'))->toBe("'-2+3")->and(ExportWriter::safe('@SUM(A1)'))->toBe("'@SUM(A1)")
        ->and(ExportWriter::safe("\tcmd"))->toBe("'\tcmd")->and(ExportWriter::safe('Mathematics'))->toBe('Mathematics')
        ->and(ExportWriter::safe(-5))->toBe(-5)->and(ExportWriter::safe(null))->toBeNull();
});

it('constructs definitions without touching the database', function (): void {
    $queries = queriesDuring(static function (): void {
        foreach (glob(dirname(__DIR__).'/Fixtures/Analytics/*/*.php') ?: [] as $file) {
            (require $file)->validate();
        }

        new Dashboard('d', new Kpi('attempts'));
    });

    expect($queries)->toBe([]);
});

it('validates configuration and never lets it remove limits', function (array $config, string $message): void {
    expect(fn () => AnalyticsConfig::fromArray($config))->toThrow(InvalidDefinitionException::class, $message);
})->with([
    [['queries' => ['max_rows' => 0]], 'queries.max_rows'],
    [['queries' => ['max_chart_points' => 1_000_000]], 'max_chart_points'],
    [['security' => ['require_authorization' => 'no']], 'must be true or false'],
    [['timezone' => 'Mars/Olympus'], 'time zone'],
    [['exports' => ['directory' => '../../etc']], 'relative path'],
]);
