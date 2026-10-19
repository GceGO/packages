<?php

declare(strict_types=1);

use Gcego\Analytics\Events\MetricCalculated;
use Gcego\Analytics\Exceptions\AnalyticsQueryException;
use Gcego\Analytics\Filters\DateRange;
use Gcego\Analytics\Tests\Fixtures\Models\AssessmentResult;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['teacher']);
});

it('calculates every aggregate in the database, after tenant scopes and soft deletes', function (): void {
    $value = static fn (string $metric) => analytics()->metric($metric)->calculate(range: 'last_30_days');

    expect($value('attempts')->value)->toBe(6)
        ->and($value('completed_attempts')->value)->toBe(5)
        ->and($value('average_score')->value)->toBe(70.0)
        ->and($value('best_score')->value)->toEqual(90)
        ->and($value('lowest_score')->value)->toEqual(50)
        ->and($value('total_time')->value)->toBe(3600)
        ->and($value('total_time')->formatted)->toBe('1h 0m')
        ->and($value('active_students')->value)->toBe(3)
        ->and(analytics()->metric('attempts')->calculate()->value)->toBe(7)
        ->and(analytics()->metric('total_subjects')->calculate()->value)->toBe(3);
});

it('never loads rows into PHP to aggregate', function (): void {
    $queries = queriesDuring(static fn () => analytics()->metric('average_score')->calculate(range: 'last_30_days'));
    $sql = implode("\n", $queries);

    expect($sql)->toContain('AVG(')->toContain('"deleted_at" is null')->toContain('"institution_id" = ?')
        ->and($sql)->not->toContain('select * from "assessment_results"');
});

it('combines metrics into ratios, percentages and derived values', function (): void {
    expect(analytics()->metric('completion_rate')->calculate(range: 'last_30_days')->value)->toBe(83.3)
        ->and(analytics()->metric('completion_rate')->calculate(range: 'last_30_days')->formatted)->toBe('83.3 %')
        ->and(analytics()->metric('completion_ratio')->calculate(range: 'last_30_days')->value)->toBe(0.833)
        ->and(analytics()->metric('attempts_per_student')->calculate(range: 'last_30_days')->value)->toBe(2.0);
});

it('distinguishes no value from zero, and never divides by zero', function (): void {
    $empty = DateRange::between('2025-06-01', '2025-06-30', 'UTC');

    expect(analytics()->metric('attempts')->calculate(range: $empty)->value)->toBe(0)
        ->and(analytics()->metric('average_score')->calculate(range: $empty)->value)->toBeNull()
        ->and(analytics()->metric('average_score')->calculate(range: $empty)->formatted)->toBe('—')
        ->and(analytics()->metric('completion_rate')->calculate(range: $empty)->value)->toBeNull()
        ->and(analytics()->metric('attempts_per_student')->calculate(range: $empty)->value)->toBeNull();
});

it('ignores null scores in averages (an unfinished essay is not a zero)', function (): void {
    $result = analytics()->metric('average_score')->calculate(range: 'last_30_days', groupBy: 'subject_id');

    expect(collect($result->groups)->pluck('value', 'label')->all())->toBe(['Mathematics' => 80.0, 'Chemistry' => 55.0, 'Literature' => null]);
});

it('builds gap-filled daily series, zero for counts and empty for averages', function (): void {
    $attempts = analytics()->metric('daily_attempts')->calculate(range: 'last_7_days');
    $scores = analytics()->metric('average_score')->calculate(range: 'last_7_days', interval: 'day');

    expect(collect($attempts->series)->pluck('value', 'key')->all())->toBe(['2026-03-09' => 0, '2026-03-10' => 1, '2026-03-11' => 2, '2026-03-12' => 1, '2026-03-13' => 0, '2026-03-14' => 1, '2026-03-15' => 0])
        ->and(collect($scores->series)->pluck('value', 'key')->all())->toMatchArray(['2026-03-09' => null, '2026-03-10' => 80.0, '2026-03-11' => 65.0, '2026-03-12' => null, '2026-03-14' => 50.0])
        ->and($attempts->series[1]['label'])->toBe('10 Mar 2026');
});

it('buckets by the report\'s time zone', function (): void {
    $douala = analytics()->metric('attempts')->calculate(range: DateRange::between('2026-03-11', '2026-03-12', 'Africa/Douala'), interval: 'day');
    $utc = analytics()->metric('attempts')->calculate(range: DateRange::between('2026-03-11', '2026-03-12', 'UTC'), interval: 'day');

    // Student2's 23:30 UTC attempt is on the 12th in Douala (UTC+1); the 12th at 08:00 UTC stays on the 12th.
    expect(collect($douala->series)->pluck('value', 'key')->all())->toBe(['2026-03-11' => 1, '2026-03-12' => 2])
        ->and(collect($utc->series)->pluck('value', 'key')->all())->toBe(['2026-03-11' => 2, '2026-03-12' => 1]);
});

it('groups by every interval', function (string $interval, array $expected): void {
    $result = analytics()->metric('attempts')->calculate(range: DateRange::between('2026-01-01', '2026-03-15', 'UTC'), interval: $interval);

    expect(array_filter(collect($result->series)->pluck('value', 'key')->all()))->toBe($expected);
})->with([
    'weekly' => ['week', ['2026-01-19' => 1, '2026-02-16' => 1, '2026-03-09' => 5]],
    'monthly' => ['month', ['2026-01' => 1, '2026-02' => 1, '2026-03' => 5]],
    'quarterly' => ['quarter', ['2026-Q1' => 7]],
    'yearly' => ['year', ['2026' => 7]],
]);

it('groups by hour', function (): void {
    $result = analytics()->metric('attempts')->calculate(range: DateRange::between('2026-03-11', '2026-03-11', 'UTC'), interval: 'hour');

    expect(array_filter(collect($result->series)->pluck('value', 'key')->all()))->toBe(['2026-03-11 10:00' => 1, '2026-03-11 23:00' => 1])
        ->and($result->series)->toHaveCount(24);
});

it('compares with the previous period and the same period last year', function (): void {
    $previous = analytics()->metric('attempts')->calculate(range: 'last_30_days', comparison: 'previous_period');
    $lastYear = analytics()->metric('attempts')->calculate(range: 'last_30_days', comparison: 'previous_year');

    expect($previous->comparison?->toArray())->toMatchArray(['previous' => 1, 'change' => 5, 'percent_change' => 500.0, 'direction' => 'up'])
        ->and($previous->comparison?->range->toArray())->toMatchArray(['from' => '2026-01-15', 'to' => '2026-02-13'])
        ->and($lastYear->comparison?->toArray())->toMatchArray(['previous' => 0, 'percent_change' => null, 'direction' => 'up'])
        ->and(analytics()->metric('attempts')->calculate()->comparison)->toBeNull();
});

it('limits groups to the top N and keeps the rest as Other', function (): void {
    $result = analytics()->metric('attempts')->calculate(range: 'last_30_days', groupBy: 'subject_id', limit: 2);

    expect(collect($result->groups)->pluck('value', 'label')->all())->toBe(['Mathematics' => 3, 'Chemistry' => 2, 'Other' => 1])
        ->and($result->truncated)->toBeTrue();
});

it('applies filters of the metric and of the caller', function (): void {
    expect(analytics()->metric('attempts')->calculate(range: 'last_30_days', filters: ['level' => 'a'])->value)->toBe(3)
        ->and(analytics()->metric('attempts')->calculate(range: 'last_30_days', filters: ['kind' => ['objective', 'essay']])->value)->toBe(4)
        ->and(analytics()->metric('completed_attempts')->calculate(range: 'last_30_days', filters: ['level' => 'a'])->value)->toBe(2)
        ->and(fn () => analytics()->metric('attempts')->calculate(filters: ['nonexistent' => 1]))->toThrow(AnalyticsQueryException::class, 'not an available filter')
        ->and(fn () => analytics()->metric('attempts')->calculate(filters: ['level' => 'x']))->toThrow(AnalyticsQueryException::class, 'not an option');
});

it('caches per audience and serves a hit without querying', function (): void {
    Event::fake([MetricCalculated::class]);
    $first = analytics()->metric('average_score')->calculate(range: 'last_30_days');
    $queries = queriesDuring(static fn () => analytics()->metric('average_score')->calculate(range: 'last_30_days'));
    $second = analytics()->metric('average_score')->calculate(range: 'last_30_days');

    expect($first->cached)->toBeFalse()->and($second->cached)->toBeTrue()->and($second->value)->toBe(70.0)
        ->and(array_filter($queries, static fn (string $sql): bool => str_contains($sql, 'assessment_results')))->toBe([]);
    Event::assertDispatched(MetricCalculated::class, static fn (MetricCalculated $event): bool => $event->cached && $event->metric === 'average_score');

    AssessmentResult::query()->where('score', 50)->update(['score' => 100]);
    expect(analytics()->metric('average_score')->calculate(range: 'last_30_days')->value)->toBe(70.0);

    analytics()->flush('assessment-results');
    expect(analytics()->metric('average_score')->calculate(range: 'last_30_days')->value)->toBe(80.0);
});

it('bypasses the cache when it is disabled', function (): void {
    $this->reboot(['gcego-analytics.cache.enabled' => false]);
    $world = world();
    actAs($world['teacher']);
    analytics()->metric('attempts')->calculate(range: 'last_30_days');

    expect(analytics()->metric('attempts')->calculate(range: 'last_30_days')->cached)->toBeFalse();
});
