<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Gcego\Components\Properties\Property;
use Gcego\Widgets\Charts\Chart;
use Gcego\Widgets\Charts\ChartDataNormalizer;
use Gcego\Widgets\Charts\ChartDataset;
use Gcego\Widgets\Charts\ChartJsAdapter;
use Gcego\Widgets\Charts\PieChart;
use Gcego\Widgets\Configuration\Trend;
use Gcego\Widgets\Data\DateRange;
use Gcego\Widgets\Data\WidgetDataResult;
use Gcego\Widgets\Exceptions\InvalidWidgetStateException;
use Gcego\Widgets\Exceptions\WidgetDataException;
use Gcego\Widgets\Progress\Goal;
use Gcego\Widgets\Progress\Progress;
use Gcego\Widgets\Statistics\TrendResult;
use Gcego\Widgets\Support\KpiFormat;
use Gcego\Widgets\WidgetsConfig;

it('formats KPIs with intl in any currency', function (mixed $value, string $format, ?int $decimals, string $expected): void {
    app()->setLocale('en');

    expect(KpiFormat::format($value, $format, $decimals, 'XAF'))->toBe($expected);
})->with([
    'integer' => [1234567, 'number', null, '1,234,567'],
    'negative' => [-1500, 'number', null, '-1,500'],
    'decimal' => [3.14159, 'decimal', 2, '3.14'],
    'percent of a fraction' => [0.725, 'percent', 1, '72.5%'],
    'currency XAF' => ['25000.00', 'currency', null, "FCFA\u{00A0}25,000"],
    'compact' => [1530000, 'compact', null, '1.5M'],
    'duration' => [3900, 'duration', null, '1h 5m'],
    'ratio' => [2.5, 'ratio', null, '2.5 : 1'],
    'numeric string' => ['42', 'number', null, '42'],
]);

it('keeps null values null and handles other currencies', function (): void {
    app()->setLocale('en');

    expect(KpiFormat::format(null, 'number', null, 'XAF'))->toBeNull()
        ->and(KpiFormat::format('n/a', 'currency', null, 'XAF'))->toBeNull()
        ->and(KpiFormat::format(19.99, 'currency', null, 'EUR'))->toBe('€19.99')
        ->and(KpiFormat::format(72, 'percent', null, 'XAF', 100))->toBe('72%');
});

it('computes trends with explicit meaning', function (): void {
    $up = TrendResult::between(120, 100, new Trend);
    $badUp = TrendResult::between(120, 100, new Trend(increase: 'negative'));
    $down = TrendResult::between(80, 100, new Trend(increase: 'negative'));
    $flat = TrendResult::between(5, 5, new Trend);
    $fromZero = TrendResult::between(3, 0, new Trend);

    expect([$up?->direction, $up?->percent, $up?->sentiment, $up?->tone()])->toBe(['up', 20.0, 'positive', 'success'])
        ->and([$badUp?->sentiment, $badUp?->tone()])->toBe(['negative', 'danger'])
        ->and([$down?->direction, $down?->sentiment])->toBe(['down', 'positive'])
        ->and($flat?->sentiment)->toBe('neutral')
        ->and($fromZero?->percent)->toBeNull()
        ->and(TrendResult::between(null, 5, new Trend))->toBeNull();
});

it('computes progress safely', function (): void {
    expect(Progress::percent(72, 0, 100))->toBe(['percent' => 72.0, 'clamped' => 72.0])
        ->and(Progress::percent(150, 0, 100))->toBe(['percent' => 150.0, 'clamped' => 100.0])
        ->and(Progress::percent(-10, 0, 100))->toBe(['percent' => -10.0, 'clamped' => 0.0])
        ->and(Progress::percent(5, 0, 0))->toBe(['percent' => null, 'clamped' => null])
        ->and(Progress::percent(null, 0, 100))->toBe(['percent' => null, 'clamped' => null]);
});

it('evaluates goals in both directions', function (): void {
    $reach = new Goal('Enrolment', new Property(Goal::Target, 100), new Property(Goal::Deadline, '2026-06-30'));
    $limit = new Goal('Absences', new Property(Goal::Target, 50), new Property(Goal::Direction, 'at_most'));
    $now = CarbonImmutable::parse('2026-06-15');

    expect($reach->evaluate(120, 100, $now))->toMatchArray(['status' => 'achieved', 'remaining' => 0, 'clamped' => 100.0])
        ->and($reach->evaluate(40, 100, $now))->toMatchArray(['status' => 'on_track', 'remaining' => 60])
        ->and($reach->evaluate(40, 100, CarbonImmutable::parse('2026-07-01'))['status'])->toBe('behind')
        ->and($limit->evaluate(30, 50)['status'])->toBe('within')
        ->and($limit->evaluate(60, 50)['status'])->toBe('exceeded')
        ->and($reach->evaluate(10, null)['status'])->toBe('unknown')
        ->and($reach->evaluate(10, 0)['status'])->toBe('unknown');
});

it('resolves date ranges in the display zone with comparison periods', function (): void {
    $range = new DateRange;
    $now = CarbonImmutable::parse('2026-06-15 23:30:00', 'UTC');
    $douala = $range->resolve('today', null, null, 'Africa/Douala', 732, $now);

    expect($douala->from->toDateTimeString())->toBe('2026-06-15 23:00:00')
        ->and($douala->to->toDateTimeString())->toBe('2026-06-16 22:59:59')
        ->and($range->resolve('this_month', null, null, 'UTC', 732, $now)->toArray())->toBe(['preset' => 'this_month', 'from' => '2026-06-01', 'to' => '2026-06-30'])
        ->and($range->resolve('last_month', null, null, 'UTC', 732, $now)->toArray())->toBe(['preset' => 'last_month', 'from' => '2026-05-01', 'to' => '2026-05-31'])
        ->and($range->resolve('last_7_days', null, null, 'UTC', 732, $now)->days())->toBe(7)
        ->and($range->resolve('custom', '2026-01-01', '2026-01-31', 'UTC', 732, $now)->previous()->toArray())->toBe(['preset' => 'previous', 'from' => '2025-12-01', 'to' => '2025-12-31'])
        ->and($range->resolve(null, null, null, 'UTC', 732, $now)->preset)->toBe('last_30_days');
});

it('refuses invalid ranges', function (array $state, DateRange $range): void {
    expect(fn () => $range->resolve($state[0], $state[1] ?? null, $state[2] ?? null, 'UTC', 31))->toThrow(InvalidWidgetStateException::class);
})->with([
    'unknown preset' => [['forever'], new DateRange],
    'not offered' => [['this_year'], new DateRange(default: 'today', presets: ['today'])],
    'custom not allowed' => [['custom', '2026-01-01', '2026-01-02'], new DateRange(custom: false)],
    'bad date' => [['custom', '2026-02-30', '2026-03-01'], new DateRange],
    'reversed' => [['custom', '2026-03-01', '2026-01-01'], new DateRange],
    'too long' => [['custom', '2025-01-01', '2026-01-01'], new DateRange],
    'injection' => [['custom', "2026-01-01' OR 1=1", '2026-01-02'], new DateRange],
]);

it('normalizes chart data and refuses anything unsafe', function (): void {
    $normalizer = new ChartDataNormalizer(new WidgetsConfig(maxChartPoints: 3, maxDatasets: 2));
    $chart = new Chart('Enrolment');
    $data = $normalizer->normalize($chart, WidgetDataResult::chart(["Jan\x00", 'Feb'], [new ChartDataset('A', [1, '2.5']), new ChartDataset('B', [null, 3], 'url(x)')]));

    expect($data->labels)->toBe(['Jan', 'Feb'])
        ->and($data->datasets[0]->data)->toBe([1, 2.5])
        ->and($data->datasets[1]->tone)->toBe('success')
        ->and(fn () => $normalizer->normalize($chart, WidgetDataResult::chart(['a', 'b', 'c', 'd'], [])))->toThrow(WidgetDataException::class)
        ->and(fn () => $normalizer->normalize($chart, WidgetDataResult::chart(['a'], [new ChartDataset('A', [1]), new ChartDataset('B', [1]), new ChartDataset('C', [1])])))->toThrow(WidgetDataException::class)
        ->and(fn () => $normalizer->normalize($chart, WidgetDataResult::chart(['a'], [new ChartDataset('A', [1, 2])])))->toThrow(WidgetDataException::class)
        ->and(fn () => $normalizer->normalize($chart, WidgetDataResult::chart(['a'], [new ChartDataset('A', ['<script>'])])))->toThrow(WidgetDataException::class) // @phpstan-ignore argument.type
        ->and(fn () => $normalizer->normalize(new PieChart('Share'), WidgetDataResult::series('A', ['x' => -1])))->toThrow(WidgetDataException::class)
        ->and(fn () => $normalizer->normalize(new PieChart('Share'), WidgetDataResult::chart(['a'], [new ChartDataset('A', [1]), new ChartDataset('B', [1])])))->toThrow(WidgetDataException::class);
});

it('emits a plain, allowlisted chart description', function (): void {
    $chart = new Chart('Payments', new Property(Chart::Type, 'bar'), new Property(Chart::Horizontal, true), new Property(Chart::Format, 'currency'));
    $data = (new ChartDataNormalizer(new WidgetsConfig))->normalize($chart, WidgetDataResult::series('Paid', ['Jan' => 10]));
    $config = (new ChartJsAdapter(new WidgetsConfig))->config($chart, $data);

    expect(array_keys($config))->toBe(['type', 'labels', 'datasets', 'horizontal', 'stacked', 'legend', 'format'])
        ->and($config['horizontal'])->toBeTrue()
        ->and($config['format'])->toMatchArray(['style' => 'currency', 'currency' => 'XAF'])
        ->and((string) json_encode($config))->not->toContain('function');
});
