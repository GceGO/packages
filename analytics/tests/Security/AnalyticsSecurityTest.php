<?php

declare(strict_types=1);

use Gcego\Analytics\Events\AnalyticsQueryRejected;
use Gcego\Analytics\Exceptions\AnalyticsAuthorizationException;
use Gcego\Analytics\Exceptions\AnalyticsQueryException;
use Gcego\Analytics\Exports\ExportManager;
use Gcego\Analytics\Filters\AnalyticsFilter;
use Gcego\Analytics\Filters\DateRange;
use Gcego\Analytics\Filters\FilterOperator;
use Gcego\Analytics\Measures\Aggregate;
use Gcego\Analytics\Measures\Measure;
use Gcego\Analytics\Metrics\Metric;
use Gcego\Analytics\Queries\AnalyticsQuery;
use Gcego\Analytics\Registry\AnalyticsDefinitions;
use Gcego\Analytics\Reports\ReportRequest;
use Gcego\Analytics\Scheduling\ReportScheduleManager;
use Gcego\Analytics\Tests\Fixtures\Models\AssessmentResult;
use Gcego\Analytics\Tests\Fixtures\Models\Membership;
use Gcego\Analytics\Tests\Fixtures\Models\Subject;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Security\Requirements\Ability;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->world = world();
});

function rawQuery(array $measures, array $dimensions = [], array $filters = [], ?DateRange $range = null, ...$rest): AnalyticsQuery
{
    return new AnalyticsQuery(app(AnalyticsDefinitions::class)->dataset('assessment-results'), $measures, $dimensions, $filters, $range, ...$rest);
}

it('denies metrics, reports and dashboards to viewers without the requirements', function (): void {
    actAs($this->world['visitor']);

    expect(fn () => analytics()->metric('attempts')->calculate())->toThrow(AnalyticsAuthorizationException::class)
        ->and(fn () => analytics()->report('subject-performance')->run())->toThrow(AnalyticsAuthorizationException::class)
        ->and(fn () => analytics()->dashboard('academic-overview'))->toThrow(AnalyticsAuthorizationException::class)
        ->and(fn () => analytics()->schema('assessment-results'))->toThrow(AnalyticsAuthorizationException::class);

    actAs(null);
    expect(fn () => analytics()->metric('attempts')->calculate())->toThrow(AnalyticsAuthorizationException::class);
});

it('denies definitions that declare no authorization', function (): void {
    app(RegistryInterface::class)->register(new Metric(name: 'open_count', model: Subject::class));
    actAs($this->world['admin']);

    expect(fn () => analytics()->metric('open_count')->calculate())->toThrow(AnalyticsAuthorizationException::class);
});

it('refuses a forged tenant: the viewer must be a member of it', function (): void {
    expect(fn () => analytics()->as($this->world['teacher'], '2')->metric('attempts')->calculate())->toThrow(AnalyticsAuthorizationException::class);

    $south = analytics()->as($this->world['south'], '2')->metric('attempts')->calculate();
    expect($south->value)->toBe(1);
});

it('isolates tenants in aggregates, labels and cached results', function (): void {
    actAs($this->world['teacher']);
    $first = analytics()->metric('average_score')->calculate(range: 'last_30_days', groupBy: 'subject_id');
    actAs($this->world['south'], 2);
    $second = analytics()->metric('average_score')->calculate(range: 'last_30_days', groupBy: 'subject_id');

    expect(collect($first->groups)->pluck('label')->all())->toBe(['Mathematics', 'Chemistry', 'Literature'])
        ->and($second->cached)->toBeFalse()
        ->and(collect($second->groups)->pluck('value', 'label')->all())->toBe(['Biology' => 99.0]);
});

it('never shares cached results across permission contexts', function (): void {
    actAs($this->world['admin']);
    $admin = analytics()->report('subject-performance')->run(range: 'last_30_days');
    actAs($this->world['teacher']);
    $teacher = analytics()->report('subject-performance')->run(range: 'last_30_days');

    expect(array_column($admin->columns, 'name'))->toContain('contacts')
        ->and($teacher->cached)->toBeFalse()
        ->and(array_column($teacher->columns, 'name'))->not->toContain('contacts')
        ->and(json_encode($teacher->toArray()))->not->toContain('example.test');
});

it('scopes records before aggregating: students see only their own attempts', function (): void {
    actAs($this->world['student2']);

    expect(analytics()->metric('attempts')->calculate(range: 'last_30_days')->value)->toBe(2)
        ->and(analytics()->metric('active_students')->calculate(range: 'last_30_days')->value)->toBe(1)
        ->and(collect(analytics()->metric('attempts')->calculate(range: 'last_30_days', groupBy: 'subject_id')->groups)->pluck('label')->all())->toEqualCanonicalizing(['Mathematics', 'Literature']);
});

it('suppresses groups smaller than the dataset\'s minimum size', function (): void {
    actAs($this->world['teacher']);
    $groups = collect(analytics()->metric('small_class_score')->calculate(range: 'last_30_days', groupBy: 'subject_id')->groups)->keyBy('label');

    expect($groups['Mathematics']['value'])->toBe(80.0)->and($groups['Mathematics']['suppressed'])->toBeFalse()
        ->and($groups['Chemistry']['value'])->toBeNull()->and($groups['Chemistry']['suppressed'])->toBeTrue()
        ->and($groups['Chemistry']['formatted'])->toBe('Hidden (small group)')
        ->and(analytics()->metric('small_class_score')->calculate(range: DateRange::between('2026-03-14', '2026-03-14', 'UTC'))->suppressed)->toBeTrue();
});

it('refuses sensitive fields, restricted dimensions and undeclared columns', function (): void {
    actAs($this->world['teacher']);
    Event::fake([AnalyticsQueryRejected::class]);

    expect(fn () => analytics()->query(rawQuery([new Measure('emails', Aggregate::CountDistinct, 'email')])))->toThrow(AnalyticsQueryException::class, 'not an available field')
        ->and(fn () => analytics()->query(rawQuery([Measure::count()], ['institution_id'])))->toThrow(AnalyticsQueryException::class, 'not an available dimension')
        ->and(fn () => analytics()->query(rawQuery([new Measure('x', Aggregate::Sum, 'password')])))->toThrow(AnalyticsQueryException::class)
        ->and(fn () => analytics()->query(rawQuery([Measure::count()], filters: [new AnalyticsFilter('email', FilterOperator::Equals, 'student1@example.test')])))->toThrow(AnalyticsQueryException::class)
        ->and(fn () => analytics()->query(rawQuery([new Measure('x', Aggregate::Maximum, 'time_spent')])))->toThrow(AnalyticsQueryException::class, 'not allowed')
        ->and(analytics()->schema('assessment-results')->toArray()['fields'])->not->toContain(fn ($field) => $field['name'] === 'email');

    Event::assertDispatched(AnalyticsQueryRejected::class, static fn (AnalyticsQueryRejected $event): bool => $event->reason === 'field');

    actAs($this->world['admin']);
    expect(analytics()->query(rawQuery([new Measure('emails', Aggregate::CountDistinct, 'email')]))->first()['emails'])->toBe(3);
});

it('binds hostile filter values and rejects hostile identifiers', function (string $value): void {
    actAs($this->world['teacher']);
    $rows = analytics()->query(rawQuery([Measure::count()], filters: [new AnalyticsFilter('kind', FilterOperator::In, ['objective'])], range: DateRange::preset('last_30_days', 'UTC')));

    expect($rows->first()['value'])->toBe(3)
        ->and(fn () => analytics()->report('subject-performance')->run(filters: ['level' => $value]))->toThrow(AnalyticsQueryException::class)
        ->and(fn () => analytics()->query(rawQuery([Measure::count()], [$value])))->toThrow(AnalyticsQueryException::class)
        ->and(fn () => analytics()->report('subject-performance')->run(sort: $value))->toThrow(AnalyticsQueryException::class)
        ->and(fn () => analytics()->report('subject-performance')->run(filters: [$value => 'o']))->toThrow(AnalyticsQueryException::class)
        ->and(AssessmentResult::query()->withTrashed()->count())->toBe(9);
})->with([
    "o' OR '1'='1",
    'level; DROP TABLE assessment_results; --',
    'level) UNION SELECT password FROM users --',
    '"; DELETE FROM users; --',
]);

it('binds string filter values instead of interpolating them', function (): void {
    actAs($this->world['teacher']);
    $queries = queriesDuring(static fn () => analytics()->report('subject-performance')->run(filters: ['score' => '1 OR 1=1']));

    expect($queries)->toBe([]);
})->throws(AnalyticsQueryException::class, 'Use a number');

it('bounds periods, points, pages and groups on the server', function (): void {
    actAs($this->world['teacher']);

    expect(fn () => analytics()->metric('attempts')->calculate(range: DateRange::between('2010-01-01', '2026-03-15', 'UTC')))->toThrow(AnalyticsQueryException::class, 'at most 1830 days')
        ->and(fn () => analytics()->metric('attempts')->calculate(range: DateRange::between('2025-01-01', '2026-03-15', 'UTC'), interval: 'hour'))->toThrow(AnalyticsQueryException::class, 'more than 1000 points')
        ->and(fn () => analytics()->query(rawQuery([Measure::count()], ['level'], limit: 20000)))->toThrow(AnalyticsQueryException::class, 'At most 10000 rows')
        ->and(fn () => analytics()->query(rawQuery([Measure::count()], ['level'], limit: 10, offset: 9995)))->toThrow(AnalyticsQueryException::class, 'first 10000 rows')
        ->and(fn () => analytics()->query(rawQuery([Measure::count()], ['level', 'kind', 'status', 'subject_id'])))->toThrow(AnalyticsQueryException::class, 'at most 3')
        ->and(fn () => analytics()->metric('attempts')->calculate(groupBy: 'subject_id', limit: 500))->toThrow(AnalyticsQueryException::class);
});

it('caps grouping cardinality and reports truncation', function (): void {
    $this->reboot(['gcego-analytics.queries.max_group_cardinality' => 2]);
    $world = world();
    actAs($world['teacher']);

    $rows = analytics()->query(rawQuery([Measure::count()], ['subject_id']));

    expect($rows->rows)->toHaveCount(2)->and($rows->truncated)->toBeTrue();
});

it('refuses unauthorized scheduled recipients and exports of reports the viewer may not export', function (): void {
    actAs($this->world['teacher']);
    $levels = app(AnalyticsDefinitions::class)->report('level-performance');

    expect(fn () => app(ExportManager::class)->request($levels, new ReportRequest, 'csv', analytics()->context()))->toThrow(AnalyticsAuthorizationException::class)
        ->and(fn () => app(ReportScheduleManager::class)->create(app(AnalyticsDefinitions::class)->report('subject-performance'), analytics()->context(), 'daily', '08:00', [$this->world['visitor']]))->toThrow(AnalyticsQueryException::class, 'recipient');
});

it('denies models gcego/data denies (no policy), as an analytics refusal', function (): void {
    app(RegistryInterface::class)->register(new Metric('memberships', Membership::class, new Ability('analytics.view')));
    actAs($this->world['admin']);

    expect(fn () => analytics()->metric('memberships')->calculate())->toThrow(AnalyticsAuthorizationException::class, 'see this data');
});
