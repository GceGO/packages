<?php

declare(strict_types=1);

use Gcego\Core\Gcego;
use Gcego\Widgets\Livewire\WidgetHost;
use Gcego\Widgets\Tests\Fixtures\Models\Student;
use Gcego\Widgets\WidgetsServiceProvider;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['teacher']);
});

function host(string $widget, array $params = []): Testable
{
    return Livewire::test(WidgetHost::class, ['widget' => $widget, ...$params]);
}

function chartConfig(Testable $host): array
{
    preg_match('/data-gcego-chart-config>(.*?)<\/script>/s', $host->html(), $match);

    return json_decode($match[1] ?? 'null', true) ?? [];
}

it('mounts a widget with its date range controls and refresh button', function (): void {
    $host = host('enrolment');

    expect($host->get('range'))->toBe('this_year')
        ->and($host->html())->toContain('wire:model.live="range"')
        ->and($host->html())->toContain('Last 30 days')
        ->and($host->html())->toContain('wire:click="refresh"')
        ->and($host->html())->not->toContain('wire:poll')
        ->and(chartConfig($host)['labels'])->toHaveCount(12);
});

it('changes the period on the server and re-keys the chart', function (): void {
    $host = host('enrolment');
    $before = $host->html();
    $host->set('range', 'last_month');

    expect(chartConfig($host)['labels'])->toBe(['May 2026'])
        ->and($host->html())->not->toBe($before);

    $host->set('range', 'custom')->set('from', '2026-02-01')->set('to', '2026-03-31');

    expect(chartConfig($host)['labels'])->toBe(['Feb 2026', 'Mar 2026'])
        ->and(chartConfig($host)['datasets'][0]['data'])->toBe([1, 2]);
});

it('resets invalid or forged ranges instead of trusting them', function (string $property, string $value): void {
    $host = host('enrolment')->set($property === 'from' ? 'range' : $property, $property === 'from' ? 'custom' : $value);

    if ($property === 'from') {
        $host->set('from', $value)->set('to', '2026-01-01');
    }

    expect($host->get('range'))->toBe('this_year')
        ->and($host->html())->toContain('was not valid and was reset');
})->with([['range', 'forever'], ['range', "this_year' OR 1=1"], ['from', '2020-01-01']]);

it('refreshes with fresh data and re-authorizes every request', function (): void {
    $host = host('total_students');

    expect($host->html())->toContain('<data value="4">');

    Student::query()->create(['name' => 'Eve', 'institution_id' => 1]);

    expect($host->call('refresh')->html())->toContain('<data value="5">');

    Gate::before(fn ($user, string $ability) => $ability === 'viewAny' ? false : null);
    actAs($this->world['teacher']);

    $host->call('refresh')->assertForbidden();
});

it('polls only when asked, never faster than the minimum, and listens to declared events', function (): void {
    $host = host('live_students');

    expect($host->html())->toContain('wire:poll.30s.visible="refresh"')
        ->and(host('total_students')->html())->not->toContain('wire:poll');

    $host->dispatch('students-changed')->assertOk();
});

it('pages through activity, bounded', function (): void {
    $host = host('recent_activity');

    expect($host->html())->toContain('Timetable published')
        ->and($host->html())->not->toContain('Term opened')
        ->and($host->html())->toContain('wire:click="olderPage"');

    $host->call('olderPage');

    expect($host->html())->toContain('Term opened')
        ->and($host->html())->not->toContain('Timetable published');

    $host->call('newerPage')->call('newerPage');

    expect($host->get('page'))->toBe(1);
});

it('refuses unknown or unauthorized widgets at mount', function (): void {
    host('nope')->assertStatus(404);
    host('finance_only')->assertForbidden();
});

it('locks identity, record and page', function (string $property, mixed $value): void {
    expect(fn () => host('selected_student', ['record' => 1])->set($property, $value))->toThrow(CannotUpdateLockedPropertyException::class);
})->with([['widget', 'finance_only'], ['record', 2], ['page', 50]]);

it('keeps the snapshot small and free of data', function (): void {
    Gate::define('dashboard.finance', fn (): bool => true);
    actAs($this->world['bursar']);
    $snapshot = (string) json_encode(host('overview')->snapshot);

    expect($snapshot)->not->toContain('40000')
        ->and($snapshot)->not->toContain('Amina')
        ->and(strlen($snapshot))->toBeLessThan(1000);
});

it('follows the tenant of each request', function (): void {
    $host = host('total_students');

    expect($host->html())->toContain('<data value="4">');

    actAs($this->world['south_user'], 2);

    expect(host('total_students')->html())->toContain('<data value="2">');
});

it('registers the Livewire alias and the package', function (): void {
    expect(Livewire::test(WidgetsServiceProvider::LivewireAlias, ['widget' => 'total_students'])->instance())->toBeInstanceOf(WidgetHost::class)
        ->and(app(Gcego::class)->hasPackage('gcego/widgets'))->toBeTrue()
        ->and(config('gcego-widgets.charts.default_adapter'))->toBe('chartjs');
});
