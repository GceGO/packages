<?php

declare(strict_types=1);

use Gcego\Analytics\Livewire\AnalyticsDashboardHost;
use Gcego\Analytics\Livewire\ReportViewer;
use Gcego\Analytics\Models\AnalyticsExport;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['teacher']);
});

it('renders KPI cards, charts with data tables, and report sections', function (): void {
    Livewire::actingAs($this->world['teacher'])->test(AnalyticsDashboardHost::class, ['dashboard' => 'academic-overview'])
        ->assertSee('Academic Overview')->assertSee('Attempts')->assertSee('83.3 %')
        ->assertSee('up 500.0 % from 1')->assertSeeHtml('data-gcego-trend="up"')
        ->assertSeeHtml('x-data="gcegoChart"')->assertSeeHtml('data-gcego-chart="chartjs"')->assertSee('Show data')
        ->assertSeeHtml('<caption class="sr-only">Average score by subject</caption>')
        ->assertSeeHtml('role="status"')->assertSee('Subject Performance');
});

it('updates for a new period, comparison and filter, validating every value', function (): void {
    $component = Livewire::actingAs($this->world['teacher'])->test(AnalyticsDashboardHost::class, ['dashboard' => 'academic-overview']);

    $component->set('range', 'last_7_days')->assertSee('9 Mar 2026')->assertHasNoErrors();
    $component->set('comparison', 'none')->assertDontSeeHtml('data-gcego-trend=');
    $component->set('filters.level', 'a')->assertSee('50.0 %');
    $component->set('range', 'forever')->assertHasErrors('analytics')->assertSee('Unknown period.');
    $component->set('filters', ['institution_id' => 2])->assertHasErrors('analytics');
    $component->call('resetFilters')->assertHasNoErrors()->assertSee('83.3 %');
    $component->call('refreshData')->assertSee('Updated.');
});

it('exports a dashboard report section for viewers allowed to export', function (): void {
    Livewire::actingAs($this->world['teacher'])->test(AnalyticsDashboardHost::class, ['dashboard' => 'academic-overview'])
        ->call('export', 'subject-performance', 'csv')->assertHasNoErrors()->assertSee('Your export is ready.')->assertDispatched('gcego-analytics-export')
        ->call('export', 'level-performance', 'csv')->assertHasErrors('analytics');

    expect(AnalyticsExport::query()->count())->toBe(1);
});

it('sorts, filters, paginates and exports a report on the server', function (): void {
    $component = Livewire::actingAs($this->world['teacher'])->test(ReportViewer::class, ['report' => 'subject-performance'])
        ->assertSee('Page 1 of 2 (3 rows)')->assertSeeHtml('aria-sort="descending"');

    $component->call('goToPage', 2)->assertSee('Literature')->assertSet('page', 2);
    $component->set('filters.level', 'o')->assertSet('page', 1)->assertSee('Page 1 of 1 (2 rows)');
    $component->call('sortBy', 'attempts')->assertSet('direction', 'desc')->call('sortBy', 'attempts')->assertSet('direction', 'asc')->assertSeeHtml('aria-sort="ascending"');
    $component->set('filters.score', 'lots')->assertHasErrors('analytics')->assertSee('Use a number.');
    $component->call('resetFilters')->call('export', 'xlsx')->assertSee('Your export is ready.');

    expect(AnalyticsExport::query()->where('format', 'xlsx')->count())->toBe(1);
});

it('links drill-downs only to reports the viewer may open', function (): void {
    Livewire::actingAs($this->world['teacher'])->test(ReportViewer::class, ['report' => 'subject-performance', 'link' => '/desk/analytics/reports/:report'])
        ->assertDontSeeHtml('/desk/analytics/reports/level-performance');

    actAs($this->world['admin']);
    Livewire::actingAs($this->world['admin'])->test(ReportViewer::class, ['report' => 'subject-performance', 'link' => '/desk/analytics/reports/:report'])
        ->assertSeeHtml('/desk/analytics/reports/level-performance?filters%5Bsubject_id%5D=')->assertSee('Open Performance by Level for Mathematics');

    Livewire::actingAs($this->world['admin'])->withQueryParams(['filters' => ['subject_id' => (string) $this->world['subjects']['mathematics']->id]])->test(ReportViewer::class, ['report' => 'level-performance'])
        ->assertSee('Ordinary Level')->assertSee('Advanced Level')->assertDontSee('Chemistry');
});

it('refuses viewers without access and tampered locked state', function (): void {
    actAs($this->world['visitor']);
    Livewire::actingAs($this->world['visitor'])->test(ReportViewer::class, ['report' => 'subject-performance'])->assertForbidden();

    actAs($this->world['teacher']);
    $page = html($this->actingAs($this->world['teacher'])->withSession(tenantSession())->get('/desk/analytics/reports/subject-performance')->assertOk());
    $snapshot = snapshotOf($page, 'gcego-analytics-report');

    livewireUpdate($snapshot, [], ['report' => 'level-performance'])->assertStatus(419);
    livewireUpdate(str_replace('subject-performance', 'level-performance', $snapshot))->assertStatus(419);
    livewireUpdate($snapshot, [['method' => 'sortBy', 'params' => ['attempts']]])->assertOk();
});

it('keeps Livewire payloads small: state only, never result data', function (): void {
    $page = html($this->actingAs($this->world['teacher'])->withSession(tenantSession())->get('/desk/analytics/academic-overview')->assertOk());
    $snapshot = snapshotOf($page, 'gcego-analytics-dashboard');

    $data = json_decode($snapshot, true)['data'] ?? [];

    expect(strlen($snapshot))->toBeLessThan(1500)->and($snapshot)->not->toContain('Mathematics')->not->toContain('83.3')
        ->and(array_keys($data))->toEqualCanonicalizing(['dashboard', 'reportLink', 'range', 'from', 'to', 'comparison', 'filters', 'message']);
});
