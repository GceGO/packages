<?php

declare(strict_types=1);

use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\Slot;
use Gcego\Chroma\Tailwind;
use Gcego\Rendering\Context\RenderMode;
use Gcego\Rendering\Exceptions\RenderException;
use Gcego\Rendering\RenderEngine;
use Gcego\Widgets\Statistics\Stat;
use Gcego\Widgets\Tests\Fixtures\Models\ActivityLog;
use Gcego\Widgets\Tests\Fixtures\Models\Student;
use Illuminate\Support\Facades\Blade;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['teacher']);
});

it('renders accessible KPI cards', function (): void {
    $html = widgetHtml('total_students');

    expect($html)->toContain('<article')
        ->and($html)->toContain('aria-labelledby=')
        ->and($html)->toContain('Total Students</h3>')
        ->and($html)->toContain('<data value="4">')
        ->and($html)->toContain('rounded-gcego-lg')
        ->and($html)->not->toContain('wire:');
});

it('renders charts with a safe JSON config, a text summary and a data table', function (): void {
    $html = widgetHtml('enrolment');

    preg_match('/<script type="application\/json" data-gcego-chart-config>(.*?)<\/script>/s', $html, $match);
    $config = json_decode($match[1] ?? 'null', true);

    expect($config['type'])->toBe('line')
        ->and($config['labels'])->toHaveCount(12)
        ->and($config['labels'][2])->toBe('Mar 2026')
        ->and($config['datasets'][0]['data'])->toBe([0, 1, 2, 0, 0, 0, 0, 0, 0, 0, 0, 0])
        ->and($html)->toContain('x-data="gcegoChart"')
        ->and($html)->toContain('role="img"')
        ->and($html)->toContain('<figcaption')
        ->and($html)->toContain('Students: 12 points, total 3, highest 2 (Mar 2026)')
        ->and($html)->toContain('<table')
        ->and($html)->toContain('<th scope="row"');
});

it('renders bar, pie and doughnut charts with legends and multiple datasets', function (): void {
    preg_match('/data-gcego-chart-config>(.*?)<\/script>/s', widgetHtml('students_by_level'), $bar);
    preg_match('/data-gcego-chart-config>(.*?)<\/script>/s', widgetHtml('level_share'), $pie);
    $bar = json_decode($bar[1], true);
    $pie = json_decode($pie[1], true);

    expect($bar['datasets'])->toHaveCount(2)
        ->and($bar['stacked'])->toBeTrue()
        ->and($bar['legend'])->toBeTrue()
        ->and($bar['labels'])->toBe(['Level 1', 'Level 2', 'Level 3'])
        ->and($pie['type'])->toBe('pie')
        ->and($pie['labels'])->toBe(['1', '2', '3']);

    // The Payment policy denies the teacher: the chart is not rendered at all.
    expect(trim(widgetHtml('revenue_share')))->toBe('');

    actAs($this->world['bursar']);
    preg_match('/data-gcego-chart-config>(.*?)<\/script>/s', widgetHtml('revenue_share'), $doughnut);

    expect(json_decode($doughnut[1], true))->toMatchArray(['type' => 'doughnut', 'labels' => ['cash', 'mobile'], 'legend' => true]);
});

it('escapes everything that comes from data', function (): void {
    Student::query()->create(['name' => '</script><script>alert(1)</script>', 'level' => 9, 'enrolled_at' => '2026-01-01', 'institution_id' => 1]);
    ActivityLog::query()->create(['description' => '<img src=x onerror=alert(1)>', 'institution_id' => 1, 'created_at' => now()]);

    $html = widgetHtml('recent_activity').widgetHtml('new_students');
    preg_match('/data-gcego-chart-config>(.*?)<\/script>/s', widgetHtml('level_share'), $pie);

    expect($html)->not->toContain('<img src=x')
        ->and($html)->not->toContain('<script>alert')
        ->and($pie[1])->not->toContain('</script>')
        ->and(widgetHtml('new_students'))->toContain('&lt;/script&gt;');
});

it('renders progress and goals with accessible progress bars', function (): void {
    $progress = widgetHtml('syllabus_completion');
    $goal = widgetHtml('enrolment_target');
    $limit = widgetHtml('absence_limit');

    expect($progress)->toContain('role="progressbar"')
        ->and($progress)->toContain('aria-valuenow="72"')
        ->and($progress)->toContain('aria-valuetext="72% (72 of 100)"')
        ->and($progress)->toContain('width: 72%')
        ->and($goal)->toContain('data-gcego-goal="on_track"')
        ->and($goal)->toContain('6 to go')
        ->and($goal)->toContain('<time datetime="2099-12-31"')
        ->and($limit)->toContain('data-gcego-goal="exceeded"')
        ->and($limit)->toContain('Limit exceeded');
});

it('shows bounded, tenant-scoped activity with eager-loaded actors', function (): void {
    $queries = queriesDuring(fn () => $this->html = widgetHtml('recent_activity'));

    expect($this->html)->toContain('Timetable published')
        ->and($this->html)->toContain('Fees reminder sent')
        ->and($this->html)->not->toContain('Term opened')
        ->and($this->html)->not->toContain('South secret')
        ->and($this->html)->toContain('Teacher')
        ->and($this->html)->toContain('<time datetime=')
        ->and(count(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'from "users"'))))->toBe(1)
        ->and(implode(' ', $queries))->toContain('limit 3');
});

it('lists recent records of a blueprint and composes infolists and tables', function (): void {
    expect(widgetHtml('new_students'))->toContain('Chidi')
        ->and(widgetHtml('new_students'))->not->toContain('South One')
        ->and(widgetHtml('selected_student', record: Student::query()->where('name', 'Amina')->value('id')))->toContain('Amina')
        ->and(widgetHtml('selected_student', record: Student::query()->where('name', 'Amina')->value('id')))->not->toContain('NID-Amina')
        ->and(widgetHtml('selected_student', record: Student::query()->where('name', 'South One')->value('id')))->toContain('No data available.')
        ->and(widgetHtml('selected_student'))->toContain('No data available.')
        ->and(widgetHtml('student_list'))->toContain('wire:name="gcego-table"')
        ->and(widgetHtml('student_list'))->toContain('max-h-96');
});

it('renders registered widgets through the runtime, statically or through Livewire', function (): void {
    $static = app(RenderEngine::class)->render(widgets()->resolve('total_students'), identity: 'widget:total_students')->toHtml();
    $live = app(RenderEngine::class)->render(widgets()->resolve('enrolment'), RenderMode::Livewire, identity: 'widget:enrolment')->toHtml();
    $staticLiveCapable = app(RenderEngine::class)->render(widgets()->resolve('total_students'), RenderMode::Livewire, identity: 'widget:total_students')->toHtml();

    expect($static)->toContain('<data value="4">')
        ->and($live)->toContain('wire:name="gcego-widget"')
        ->and($staticLiveCapable)->not->toContain('wire:name="gcego-widget"')
        ->and(fn () => app(RenderEngine::class)->render(new Stat('Loose', 1))->toHtml())->toThrow(RenderException::class, 'registry identity');
});

it('renders through the anonymous Blade component', function (): void {
    $static = Blade::render('<x-gcego-widgets::widget name="total_students" />');
    $live = Blade::render('<x-gcego-widgets::widget name="enrolment" />');

    expect($static)->toContain('<data value="4">')
        ->and($static)->not->toContain('wire:id')
        ->and($live)->toContain('wire:name="gcego-widget"');
});

it('applies styling slots and instance Tailwind overrides', function (): void {
    $stat = new Stat('X', 1, new Tailwind(new Slot(Tailwind::Wrapper, ['rounded-2xl', 'p-6']), new Slot(Stat::ValueSlot, ['text-slate-900'])));
    $styling = app(ChromaManager::class);

    expect($styling->classes($stat, Tailwind::Wrapper))->toContain('rounded-2xl')
        ->and($styling->classes($stat, Stat::ValueSlot))->toContain('text-slate-900');
});
