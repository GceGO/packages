<?php

declare(strict_types=1);

use Gcego\Components\Property;
use Gcego\Layouts\Components\Grid;
use Gcego\Layouts\Components\Section;
use Gcego\Layouts\Layout;
use Gcego\Layouts\Tests\Fixtures\Models\Student;
use Gcego\Layouts\Tests\Fixtures\Policies\Denying;
use Gcego\Registry\Registry;
use Gcego\Runtime\Rendering\RenderMode;
use Gcego\Runtime\Runtime;
use Gcego\Security\Permissions\Permission;
use Gcego\Widgets\Data\Aggregate;
use Gcego\Widgets\Statistics\Stat;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['teacher']);
});

function students(RenderMode $mode = RenderMode::Livewire, string|int|null $record = 1): string
{
    return squish(app(Runtime::class)->renderAuthorized('blueprint:students', $mode, metadata: $record === null ? [] : ['record' => $record])->toHtml());
}

it('renders a registered dashboard of widgets in sections, grids and tabs', function (): void {
    $html = layoutHtml('dashboard');

    expect($html)->toContain('<h1 class="text-2xl font-semibold tracking-tight text-gcego-text">Dashboard</h1>')
        ->and($html)->toMatch('/<h2 [^>]*>Overview<\/h2>/')
        ->and($html)->toMatch('/<h3 [^>]*>Students<\/h3>.*<data value="4">4<\/data>/')
        ->and($html)->toMatch('/<h3 [^>]*>Girls<\/h3>.*<data value="2">2<\/data>/')
        ->and($html)->toContain('grid-cols-1 sm:grid-cols-2 lg:grid-cols-3')
        ->and($html)->toContain('Enrolment')
        ->and($html)->toContain('data-gcego-chart-config')
        ->and($html)->toContain('Levels')
        ->and($html)->toContain('No notes yet');
});

it('preserves child authorization: unauthorized widgets, sections and tabs leave nothing behind', function (): void {
    $html = layoutHtml('dashboard');

    expect($html)->not->toContain('Boys')
        ->and($html)->not->toContain('col-span-full')
        ->and($html)->not->toContain('Finance')
        ->and($html)->not->toContain('Fees')
        ->and($html)->not->toContain('Secret')
        ->and(substr_count($html, 'role="tab"'))->toBe(2);

    Gate::define('dashboard.boys', fn (): bool => true);
    Gate::define('dashboard.finance', fn (): bool => true);
    Gate::define('dashboard.secret', fn (): bool => true);
    actAs($this->world['bursar']);
    $html = layoutHtml('dashboard');

    expect($html)->toMatch('/<div class="min-w-0 col-span-full lg:col-span-1"><article[^>]*data-gcego-widget="boys"/')
        ->and($html)->toMatch('/<h2 [^>]*>Finance<\/h2>/')
        ->and($html)->toContain('<data value="1200">')
        ->and(substr_count($html, 'role="tab"'))->toBe(3);
});

it('keeps tenant scopes for widgets inside layouts', function (): void {
    actAs($this->world['south'], 2);

    expect(layoutHtml('dashboard'))->toMatch('/Students<\/h3>.*<data value="2">2<\/data>/')
        ->and(layoutHtml('dashboard'))->not->toContain('<data value="4">');
});

it('hides widgets whose data the user may not read, even when the layout allows', function (): void {
    app(Registry::class)->register(new Layout('staff', new Permission('staff.dashboard'),
        new Section('People', new Grid(
            new Stat(new Property(Stat::Name, 'students'), new Property(Stat::Label, 'Students'), new Aggregate(Student::class)),
            new Stat(new Property(Stat::Name, 'rooms'), new Property(Stat::Label, 'Rooms'), new Property(Stat::Value, 12)),
        )),
    ));
    Gate::define('staff.dashboard', fn (): bool => true);
    Gate::policy(Student::class, Denying::class);
    actAs($this->world['teacher']);
    $html = layoutHtml('staff');

    expect($html)->toContain('Rooms')
        ->and($html)->not->toContain('Students')
        ->and($html)->toContain('class="grid min-w-0 grid-cols-1 gap-6"');
});

it('denies registered layouts by default and refuses unknown or forged identities', function (): void {
    app(Registry::class)->register(new Layout('open', new Section('Anything', new Grid(new Stat(new Property(Stat::Name, 'x'), new Property(Stat::Label, 'X'), new Property(Stat::Value, 1))))));

    expect(statusOf(fn () => layouts()->render('open')))->toBe(403)
        ->and(statusOf(fn () => layouts()->render('nope')))->toBe(404)
        ->and(statusOf(fn () => layouts()->render('Gcego\\Layouts\\Layout')))->toBe(404)
        ->and(statusOf(fn () => layouts()->render('blueprint:students')))->toBe(404)
        ->and(statusOf(fn () => layouts()->render('layout:../dashboard')))->toBe(404)
        ->and(layouts()->identity('dashboard'))->toBe('layout:dashboard');
});

it('renders through the Blade component, mounting interactive widgets once', function (): void {
    $html = squish(Blade::render('<x-gcego-layouts::layout name="dashboard" />'));

    expect(substr_count($html, 'wire:name="gcego-widget"'))->toBe(1)
        ->and($html)->toContain('&quot;path&quot;:&quot;dashboard.trends.enrolment&quot;')
        ->and($html)->toContain('&quot;widget&quot;:&quot;layout:dashboard&quot;')
        ->and($html)->toMatch('/<data value="4">4<\/data>/')
        ->and(squish(Blade::render('<x-gcego-layouts::layout name="dashboard" :live="false" />')))->not->toContain('wire:name');
});

it('composes tables, forms, infolists and actions of a blueprint inside layouts', function (): void {
    $html = students();

    expect(substr_count($html, 'wire:name="gcego-table"'))->toBe(1)
        ->and(substr_count($html, 'wire:name="gcego-form"'))->toBe(1)
        ->and(substr_count($html, 'wire:name="gcego-infolist"'))->toBe(1)
        ->and(substr_count($html, '&quot;names&quot;:[[&quot;create&quot;]'))->toBe(1)
        ->and($html)->toMatch('/<h2 [^>]*>Students<\/h2> <\/div> <\/div> <div class="flex shrink-0 flex-wrap items-center gap-2"[^>]*><div wire:key="gcego-actions-/')
        ->and($html)->toMatch('/<aside id="[^"]+" aria-label="Summary"[^>]*>.*Enrolled.*<data value="4">4<\/data>.*<\/aside>/')
        ->and($html)->toMatch('/role="tab"[^>]*>\s*<span>Profile<\/span>/')
        ->and($html)->toMatch('/<section id="([^"]+)" aria-labelledby="\1-title"[^>]*>(?:(?!<section).)*<h2 id="\1-title"[^>]*>Edit student<\/h2>/')
        ->and($html)->toContain('Saved changes appear in the history.');
});

it('leaves out a record tab when there is no record', function (): void {
    $html = students(record: null);

    expect($html)->not->toContain('<span>Profile</span>')
        ->and($html)->toMatch('/role="tab"[^>]*aria-selected="true"[^>]*>\s*<span>Edit<\/span>/')
        ->and(substr_count($html, 'wire:name="gcego-infolist"'))->toBe(0);
});

it('never leaks hidden attributes or other tenants into the page', function (): void {
    $html = students().layoutHtml('dashboard', live: true);

    expect($html)->not->toContain('NID-')
        ->and($html)->not->toContain('South');
});

it('authorizes the blueprint before rendering any layout in it', function (): void {
    Gate::policy(Student::class, Denying::class);
    actAs($this->world['teacher']);

    expect(statusOf(fn () => students()))->toBe(403);
});
