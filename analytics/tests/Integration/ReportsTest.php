<?php

declare(strict_types=1);

use Gcego\Analytics\Dashboards\DashboardState;
use Gcego\Analytics\Definitions\AnalyticsDefinitionValidator;
use Gcego\Analytics\Events\ReportExecuted;
use Gcego\Analytics\Exceptions\AnalyticsQueryException;
use Gcego\Analytics\Exceptions\InvalidDefinitionException;
use Gcego\Analytics\Metrics\Metric;
use Gcego\Analytics\Registry\AnalyticsDefinitions;
use Gcego\Analytics\Reports\ReportResult;
use Gcego\Registry\Contracts\RegistryInterface;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['teacher']);
});

/**
 * @return array<string, array<string, mixed>>
 */
function cellsBySubject(ReportResult $result): array
{
    $rows = [];

    foreach ($result->rows as $row) {
        $rows[(string) $row['cells']['subject_id']['value']] = array_map(static fn (array $cell): mixed => $cell['value'], $row['cells']);
    }

    return $rows;
}

it('runs a report: grouped, measured, labelled, sorted and paginated on the server', function (): void {
    Event::fake([ReportExecuted::class]);
    $page = analytics()->report('subject-performance')->run(range: 'last_30_days');
    $next = analytics()->report('subject-performance')->run(range: 'last_30_days', page: 2);

    expect($page->total)->toBe(3)->and($page->lastPage())->toBe(2)->and($page->perPage)->toBe(2)
        ->and(array_keys(cellsBySubject($page)))->toBe(['Mathematics', 'Chemistry'])
        ->and(cellsBySubject($page)['Mathematics'])->toMatchArray(['attempts' => 3, 'students' => 3, 'average_score' => 80.0])
        ->and(array_keys(cellsBySubject($next)))->toBe(['Literature'])
        ->and($page->rows[0]['cells']['subject_id']['key'])->toBe((string) $this->world['subjects']['mathematics']->id)
        ->and($page->rows[0]['cells']['average_score']['formatted'])->toBe('80.0');
    Event::assertDispatched(ReportExecuted::class, static fn (ReportExecuted $event): bool => $event->report === 'subject-performance' && $event->rows === 3);
});

it('applies declared filters only, validated against the dataset', function (): void {
    $ordinary = analytics()->report('subject-performance')->run(filters: ['level' => 'o'], range: 'last_30_days');
    $kinds = analytics()->report('subject-performance')->run(filters: ['kind' => ['essay', 'structured']], range: 'last_30_days');
    $scores = analytics()->report('subject-performance')->run(filters: ['score' => '65'], range: 'last_30_days');

    expect($ordinary->total)->toBe(2)->and(cellsBySubject($ordinary)['Mathematics']['attempts'])->toBe(2)
        ->and(array_keys(cellsBySubject($kinds)))->toEqualCanonicalizing(['Chemistry', 'Literature'])
        ->and(cellsBySubject($scores)['Mathematics']['attempts'])->toBe(3)->and($scores->total)->toBe(1)
        ->and(fn () => analytics()->report('subject-performance')->run(filters: ['status' => 'completed']))->toThrow(AnalyticsQueryException::class, 'Unknown filter')
        ->and(fn () => analytics()->report('subject-performance')->run(filters: ['level' => 'z']))->toThrow(AnalyticsQueryException::class)
        ->and(fn () => analytics()->report('subject-performance')->run(filters: ['score' => 'abc']))->toThrow(AnalyticsQueryException::class, 'Use a number');
});

it('sorts by sortable columns only', function (): void {
    $byAttempts = analytics()->report('subject-performance')->run(range: 'last_30_days', sort: 'attempts', direction: 'asc');

    expect(array_keys(cellsBySubject($byAttempts)))->toBe(['Literature', 'Chemistry'])
        ->and(fn () => analytics()->report('subject-performance')->run(sort: 'nope'))->toThrow(AnalyticsQueryException::class, 'cannot be sorted');
});

it('leaves out columns on fields the viewer may not use', function (): void {
    $teacher = analytics()->report('subject-performance')->run(range: 'last_30_days');
    actAs($this->world['admin']);
    $admin = analytics()->report('subject-performance')->run(range: 'last_30_days');

    expect(array_column($teacher->columns, 'name'))->not->toContain('contacts')
        ->and(array_column($admin->columns, 'name'))->toContain('contacts')
        ->and(cellsBySubject($admin)['Mathematics']['contacts'])->toBe(3);
});

it('resolves a dashboard: each part authorized and calculated for the shared state', function (): void {
    $definition = app(AnalyticsDefinitions::class)->dashboard('academic-overview');
    $view = analytics()->dashboard('academic-overview', DashboardState::fromInput($definition, 'UTC', filters: ['level' => 'a']));
    $kpis = collect($view->kpis)->mapWithKeys(static fn (array $kpi): array => [$kpi['name'] => $kpi['result']?->value]);

    expect($view->title)->toBe('Academic Overview')
        ->and($kpis->all())->toBe(['subjects_offered' => 3, 'attempts' => 3, 'average_score' => 70.0, 'completion_rate' => 66.7])
        ->and($view->charts)->toHaveCount(2)
        ->and($view->charts[0]->config['type'] ?? null)->toBe('line')
        ->and($view->charts[0]->config['datasets'])->toHaveCount(2)
        ->and($view->charts[1]->rows[0])->toBe(['label' => 'Mathematics', 'values' => ['90.0']])
        ->and($view->charts[1]->summary)->toContain('highest Mathematics')
        ->and($view->reports[0]['result']?->total)->toBe(3)
        ->and($view->filters[0])->toMatchArray(['name' => 'level', 'options' => ['o' => 'Ordinary Level', 'a' => 'Advanced Level']]);
});

it('builds dashboard state from input, refusing what the dashboard does not declare', function (): void {
    $definition = app(AnalyticsDefinitions::class)->dashboard('academic-overview');

    expect(DashboardState::fromInput($definition, 'UTC')->range->toArray())->toMatchArray(['from' => '2026-02-14', 'to' => '2026-03-15'])
        ->and(DashboardState::fromInput($definition, 'UTC', 'custom', '2026-03-01', '2026-03-10', 'none')->comparison)->toBeNull()
        ->and(fn () => DashboardState::fromInput($definition, 'UTC', filters: ['subject_id' => 1]))->toThrow(AnalyticsQueryException::class)
        ->and(fn () => DashboardState::fromInput($definition, 'UTC', 'always'))->toThrow(AnalyticsQueryException::class)
        ->and(fn () => DashboardState::fromInput($definition, 'UTC', comparison: 'custom'))->toThrow(AnalyticsQueryException::class);
});

it('reports cross-references between definitions', function (): void {
    app(RegistryInterface::class)->register(new Metric(name: 'broken', dataset: 'assessment-results', aggregate: 'sum', field: 'user_id'));
    app(RegistryInterface::class)->register(new Metric(name: 'loop_a', aggregate: 'ratio', numerator: 'loop_b', denominator: 'attempts'));
    app(RegistryInterface::class)->register(new Metric(name: 'loop_b', aggregate: 'derived', formula: '{loop_a} + 1'));
    $validator = app(AnalyticsDefinitionValidator::class);

    expect(implode("\n", $validator->metric(app(AnalyticsDefinitions::class)->metric('broken'))))->toContain('does not allow sum')
        ->and(implode("\n", $validator->metric(app(AnalyticsDefinitions::class)->metric('loop_a'))))->toContain('depends on itself: loop_a → loop_b → loop_a')
        ->and(fn () => analytics()->metric('loop_a')->calculate())->toThrow(InvalidDefinitionException::class);
});
