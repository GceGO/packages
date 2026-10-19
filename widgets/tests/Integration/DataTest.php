<?php

declare(strict_types=1);

use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Widgets\Exceptions\InvalidWidgetStateException;
use Gcego\Widgets\Statistics\Stat;
use Gcego\Widgets\Tests\Fixtures\Models\Student;
use Gcego\Widgets\Tests\Fixtures\Providers\StudentCountProvider;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['teacher']);
    StudentCountProvider::$calls = 0;
});

it('registers widgets as a registry kind and resolves only registered identities', function (): void {
    expect(app(RegistryInterface::class)->has('widget:total_students'))->toBeTrue()
        ->and(widgets()->resolve('total_students')->title())->toBe('Total Students')
        ->and(statusOf(fn () => widgets()->resolve('widget:nope')))->toBe(404)
        ->and(statusOf(fn () => widgets()->resolve(Stat::class)))->toBe(404)
        ->and(statusOf(fn () => widgets()->resolve('blueprint:students')))->toBe(404)
        ->and(statusOf(fn () => widgets()->resolve('../../etc/passwd')))->toBe(404);
});

it('resolves provider data lazily, tenant-scoped, through gcego/data', function (): void {
    $queries = queriesDuring(fn () => widgets()->resolve('total_students'));

    expect($queries)->toBe([])
        ->and(StudentCountProvider::$calls)->toBe(0)
        ->and(widgetHtml('total_students'))->toContain('<data value="4">4</data>')
        ->and(StudentCountProvider::$calls)->toBe(1);

    actAs($this->world['south_user'], 2);

    expect(widgetHtml('total_students'))->toContain('<data value="2">2</data>');
});

it('aggregates in the database within the period, with trends and semantics', function (): void {
    $queries = queriesDuring(fn () => $this->html = widgetHtml('overview'));

    expect($this->html)->toContain('<data value="4">4</data>')
        ->and($this->html)->toContain('<data value="3">3</data>')
        ->and($this->html)->toContain('data-gcego-trend="up"')
        ->and($this->html)->toContain('data-gcego-sentiment="negative"')
        ->and($this->html)->toContain('This is a deterioration.')
        ->and($this->html)->toContain('Up 100% from the previous period.')
        ->and($this->html)->toContain('<data value="12">12</data>')
        ->and($this->html)->toContain('<data value="4">4</data>')
        ->and(implode(' ', $queries))->not->toContain('select * from "students"')
        ->and(implode(' ', $queries))->toContain('count(*)');
});

it('hides metrics the user may not see and shows them to those who may', function (): void {
    expect(widgetHtml('overview'))->not->toContain('Fees collected')
        ->and(statusOf(fn () => widgets()->render('finance_only')))->toBe(403);

    Gate::define('dashboard.finance', fn ($user): bool => $user->name === 'Bursar');
    actAs($this->world['bursar']);

    expect(widgetHtml('overview'))->toContain('Fees collected')
        ->and(widgetHtml('overview'))->toContain("FCFA\u{00A0}40,000")
        ->and(widgetHtml('finance_only'))->toContain('40,000')
        ->and(widgetHtml('finance_only'))->not->toContain('99,999')
        ->and(widgetHtml('finance_only'))->not->toContain('139,999');
});

it('never bypasses the underlying data authorization', function (): void {
    Gate::define('dashboard.finance', fn (): bool => true);
    actAs($this->world['teacher']);

    // The widget permission passes, but the Payment policy denies the teacher: the metric is hidden, not leaked.
    expect(widgetHtml('overview'))->not->toContain('40,000')
        ->and(widgetHtml('overview'))->not->toContain('Fees collected');
});

it('shows a safe error state when a provider fails', function (): void {
    $html = widgetHtml('failing');

    expect($html)->toContain('role="alert"')
        ->and($html)->toContain('could not be loaded')
        ->and($html)->not->toContain('SQLSTATE')
        ->and($html)->not->toContain('secret_column');
});

it('refuses invalid chart data from providers instead of drawing it', function (): void {
    $html = widgetHtml('broken_chart');

    expect($html)->toContain('could not be loaded')
        ->and($html)->not->toContain('alert(1)');
});

it('caches per user and tenant, never across them, and invalidates on demand', function (): void {
    widgetHtml('cached_students');
    widgetHtml('cached_students');

    expect(StudentCountProvider::$calls)->toBe(1);

    actAs($this->world['bursar']);
    widgetHtml('cached_students');

    expect(StudentCountProvider::$calls)->toBe(2);

    actAs($this->world['south_user'], 2);

    expect(widgetHtml('cached_students'))->toContain('<data value="2">2</data>')
        ->and(StudentCountProvider::$calls)->toBe(3);

    actAs($this->world['teacher']);
    Student::query()->create(['name' => 'Eve', 'institution_id' => 1]);

    expect(widgetHtml('cached_students'))->toContain('<data value="4">4</data>');

    widgets()->forget('cached_students');

    expect(widgetHtml('cached_students'))->toContain('<data value="5">5</data>');
});

it('does not cache unless the widget opts in', function (): void {
    widgetHtml('total_students');
    widgetHtml('total_students');

    expect(StudentCountProvider::$calls)->toBe(2);
});

it('denies widgets without permission and re-checks the data source', function (): void {
    Gate::before(fn ($user, string $ability) => $ability === 'viewAny' ? false : null);
    actAs($this->world['teacher']);

    expect(statusOf(fn () => widgets()->render('total_students')))->toBe(403);
});

it('builds request-scoped contexts with the period, user and tenant', function (): void {
    $widget = widgets()->resolve('enrolment');
    $context = widgets()->context('widget:enrolment', $widget, widgets()->state($widget, ['range' => 'last_month']));

    expect($context->period?->toArray())->toBe(['preset' => 'last_month', 'from' => '2026-05-01', 'to' => '2026-05-31'])
        ->and($context->user?->getAuthIdentifier())->toBe($this->world['teacher']->id)
        ->and($context->tenant?->key)->toBe(1)
        ->and(fn () => widgets()->state($widget, ['range' => 'forever']))->toThrow(InvalidWidgetStateException::class)
        ->and(fn () => widgets()->state($widget, ['page' => 9999]))->toThrow(InvalidWidgetStateException::class);
});
