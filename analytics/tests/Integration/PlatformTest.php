<?php

declare(strict_types=1);

use Gcego\Actions\ActionManager;
use Gcego\Actions\Execution\ActionRequest;
use Gcego\Actions\Execution\ActionStatus;
use Gcego\Analytics\Diagnostics\AnalyticsInspector;
use Gcego\Analytics\Exceptions\AnalyticsAuthorizationException;
use Gcego\Analytics\Exceptions\AnalyticsNotFoundException;
use Gcego\Analytics\Integration\ReportRows;
use Gcego\Analytics\Integration\Widgets\MetricTrend;
use Gcego\Analytics\Integration\Widgets\MetricValue;
use Gcego\Analytics\Metrics\Metric;
use Gcego\Analytics\Models\AnalyticsExport;
use Gcego\Analytics\Registry\AnalyticsDefinitions;
use Gcego\Analytics\Registry\AnalyticsKind;
use Gcego\Analytics\Reports\Report;
use Gcego\Analytics\Reports\ReportColumn;
use Gcego\Data\DataManager;
use Gcego\Data\Query\QueryContext;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Registry\Exceptions\InvalidDefinitionException;
use Gcego\Security\Tenancy\Tenant;
use Gcego\Widgets\Data\WidgetDataContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->world = world();
});

it('discovers analytics definitions per kind from Datasets, Metrics, Reports and Dashboards folders', function (): void {
    $definitions = app(AnalyticsDefinitions::class);

    expect($definitions->names(AnalyticsKind::Dataset))->toContain('assessment-results', 'small-classes', 'workflow-instances')
        ->and($definitions->names(AnalyticsKind::Metric))->toContain('attempts', 'completion_rate', 'total_subjects', 'pending_approvals')
        ->and($definitions->names(AnalyticsKind::Report))->toBe(['level-performance', 'subject-performance'])
        ->and($definitions->names(AnalyticsKind::Dashboard))->toBe(['academic-overview'])
        ->and(fn () => $definitions->metric('../../etc/passwd'))->toThrow(AnalyticsNotFoundException::class);
});

it('refuses invalid definitions at registration', function (): void {
    app(RegistryInterface::class)->register(new Metric(name: 'bad', aggregate: 'sum'));
})->throws(InvalidDefinitionException::class, 'exactly one of Dataset');

it('inspects definitions, authorization, tenant scopes and runtime state', function (): void {
    app(RegistryInterface::class)->register(new Report('open-report', new ReportColumn('n', aggregate: 'count'), dataset: 'assessment-results'));
    $report = app(AnalyticsInspector::class)->report();
    $byName = static fn (string $kind, string $name): array => collect($report['definitions'][$kind])->firstWhere('name', $name) ?? [];

    expect($byName('report', 'subject-performance')['problems'])->toBe([])
        ->and(implode(' ', $byName('report', 'open-report')['problems']))->toContain('has no gcego/security requirement')
        ->and(implode(' ', $byName('dataset', 'workflow-instances')['warnings']))->toContain('custom provider')
        ->and($report['config']['require_authorization'])->toBeTrue()
        ->and($report['runtime']['active_schedules'])->toBe(0);

    $this->artisan('gcego:analytics:inspect')->expectsOutputToContain('subject-performance')->assertFailed();
    $this->artisan('gcego:analytics:inspect', ['--json' => true, '--definitions' => true])->expectsOutputToContain('"academic-overview"')->assertFailed();
});

it('serves dashboards and reports inside panels, behind their authorization', function (): void {
    $dashboard = html($this->actingAs($this->world['teacher'])->withSession(tenantSession())->get('/desk/analytics/academic-overview')->assertOk());

    expect($dashboard)->toContain('Academic Overview')->toContain('Subjects offered')->toContain('83.3 %')->toContain('x-data="gcegoChart"')->toContain('Show data')
        ->toContain('/desk/analytics/reports/subject-performance');

    $this->actingAs($this->world['visitor'])->withSession(tenantSession())->get('/desk/analytics/academic-overview')->assertForbidden();
    $this->actingAs($this->world['teacher'])->withSession(tenantSession())->get('/desk/analytics/nowhere')->assertNotFound();

    $index = html($this->actingAs($this->world['teacher'])->withSession(tenantSession())->get('/desk/analytics/reports')->assertOk());
    expect($index)->toContain('Subject Performance')->not->toContain('Performance by Level');

    $report = html($this->actingAs($this->world['teacher'])->withSession(tenantSession())->get('/desk/analytics/reports/subject-performance?range=last_30_days')->assertOk());
    expect($report)->toContain('Mathematics')->toContain('aria-sort="descending"')->toContain('Export')->not->toContain('level-performance?');

    app('auth')->forgetGuards();
    $admin = html($this->actingAs($this->world['admin'])->withSession(tenantSession())->get('/desk/analytics/reports/subject-performance')->assertOk());
    expect($admin)->toContain('/desk/analytics/reports/level-performance?filters%5Bsubject_id%5D='.$this->world['subjects']['mathematics']->id);

    app('auth')->forgetGuards();
    $this->actingAs($this->world['teacher'])->withSession(tenantSession())->get('/desk/analytics/reports/level-performance')->assertForbidden();
});

it('feeds gcego/widgets KPIs and trends', function (): void {
    actAs($this->world['teacher']);
    $context = new WidgetDataContext('w', 'p', $this->world['teacher'], new Tenant('institution', 1), null, null, 1, 'en', 'UTC', app(DataManager::class));
    $value = new class extends MetricValue
    {
        protected function metric(): string
        {
            return 'attempts';
        }
    };
    $trend = new class extends MetricTrend
    {
        protected function metric(): string
        {
            return 'attempts';
        }
    };

    expect($value->resolve($context)->value)->toBe(6)->and($value->resolve($context)->previous)->toBe(1)
        ->and(array_sum($trend->resolve($context)->datasets[0]->data))->toBe(6)
        ->and((new class extends MetricValue
        {
            protected function metric(): string
            {
                return 'attempts';
            }
        })->resolve(new WidgetDataContext('w', 'p', $this->world['visitor'], new Tenant('institution', 1), null, null, 1, 'en', 'UTC', app(DataManager::class)))->value)->toBeNull();
});

it('exports from a gcego/actions action, with validated input as filters', function (): void {
    actAs($this->world['teacher']);
    $request = Request::create('/desk/subjects', 'GET');
    $request->setRouteResolver(static fn () => app('router')->getRoutes()->match($request));
    app()->instance('request', $request);

    $result = app(ActionManager::class)->execute(new ActionRequest('page:academics-subjects', 'export-performance', null, [], ['level' => 'o']));

    expect($result->status)->toBe(ActionStatus::Success)
        ->and(AnalyticsExport::query()->firstOrFail()->request['filters'] ?? null)->toBe(['level' => 'o']);
});

it('offers report rows to gcego/tables as a collection source', function (): void {
    actAs($this->world['teacher']);
    $rows = app(DataManager::class)->query(ReportRows::source('subject-performance', ['level' => 'o']), [], QueryContext::system());

    expect(collect($rows->items())->pluck('attempts', 'subject_id')->all())->toBe(['Mathematics' => 3, 'Chemistry' => 1]);
});

it('measures gcego/workflows throughput and approvals in the tenant', function (): void {
    $now = now();

    foreach ([['1', 'active'], ['1', 'completed'], ['2', 'active']] as $index => [$tenant, $status]) {
        DB::table('gcego_workflow_instances')->insert(['id' => $index + 1, 'ulid' => (string) Str::ulid(), 'workflow' => 'registration', 'version' => 1, 'structure' => 'x', 'subject_type' => 'a', 'subject_id' => (string) $index, 'tenant' => $tenant, 'state' => 'draft', 'status' => $status, 'started_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
    }

    foreach ([['1', 'approval', 'open'], ['1', 'approval', 'escalated'], ['1', 'task', 'completed'], ['1', 'task', 'open'], ['2', 'approval', 'open']] as $index => [$tenant, $kind, $status]) {
        DB::table('gcego_workflow_tasks')->insert(['ulid' => (string) Str::ulid(), 'instance_id' => $tenant === '1' ? 1 : 3, 'tenant' => $tenant, 'kind' => $kind, 'name' => 'review', 'state' => 'draft', 'status' => $status, 'priority' => 'normal', 'created_at' => $now, 'updated_at' => $now]);
    }

    actAs($this->world['admin']);

    expect(analytics()->metric('workflows_started')->calculate(range: 'last_7_days')->value)->toBe(2)
        ->and(analytics()->metric('workflows_completed')->calculate()->value)->toBe(1)
        ->and(analytics()->metric('pending_approvals')->calculate()->value)->toBe(1)
        ->and(analytics()->metric('escalation_rate')->calculate()->value)->toBe(25.0);

    actAs($this->world['teacher']);
    expect(fn () => analytics()->metric('pending_approvals')->calculate())->toThrow(AnalyticsAuthorizationException::class);
});
