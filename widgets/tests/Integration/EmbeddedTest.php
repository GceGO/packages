<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Components\Properties\Property;
use Gcego\Registry\Registry;
use Gcego\Runtime\Rendering\ContainerRenderer;
use Gcego\Runtime\Rendering\RendererRegistry;
use Gcego\Runtime\Rendering\RenderMode;
use Gcego\Runtime\Runtime;
use Gcego\Security\Permissions\Permission;
use Gcego\Security\Requirements\Ability;
use Gcego\Widgets\Charts\LineChart;
use Gcego\Widgets\Data\Aggregate;
use Gcego\Widgets\Data\DateRange;
use Gcego\Widgets\Livewire\WidgetHost;
use Gcego\Widgets\Rendering\WidgetRenderFrame;
use Gcego\Widgets\Statistics\Stat;
use Gcego\Widgets\Tests\Fixtures\Models\Payment;
use Gcego\Widgets\Tests\Fixtures\Models\Student;
use Gcego\Widgets\Tests\Fixtures\Panel;
use Gcego\Widgets\WidgetManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
    app(RendererRegistry::class)->register(Panel::class, ContainerRenderer::class);
    app(Registry::class)->register(new Blueprint('dashboard', new Ability('viewAny', Student::class),
        new Panel(new Property(Panel::Name, 'main'),
            new Stat(new Property(Stat::Name, 'students'), new Property(Stat::Label, 'Students'), new Aggregate(Student::class)),
            new Panel(new Property(Panel::Name, 'finance'), new Permission('dashboard.finance'),
                new Stat(new Property(Stat::Name, 'fees'), new Property(Stat::Label, 'Fees'), new Aggregate(Payment::class, 'sum', 'amount'), new Property(Stat::Format, 'currency')),
            ),
            new LineChart(new Property(LineChart::Name, 'enrolment'), new Property(LineChart::Label, 'Enrolment'), new Aggregate(Student::class, date: 'enrolled_at', bucket: 'month'), new DateRange(default: 'this_year')),
        ),
    ));
    actAs($this->world['teacher']);
});

function dashboard(RenderMode $mode = RenderMode::Static): string
{
    return (string) preg_replace('/\s+/', ' ', app(Runtime::class)->renderAuthorized('blueprint:dashboard', $mode)->toHtml());
}

it('renders widgets embedded in a registered blueprint, inheriting its requirements', function (): void {
    $html = dashboard();

    expect($html)->toContain('Students')
        ->and($html)->toContain('<data value="4">')
        ->and($html)->toContain('Enrolment')
        ->and($html)->not->toContain('Fees');

    Gate::define('dashboard.finance', fn (): bool => true);
    actAs($this->world['bursar']);

    expect(dashboard())->toContain('Fees')->and(dashboard())->not->toContain('99');
});

it('hides embedded widgets whose owner requirements deny them', function (): void {
    Gate::before(fn ($user, string $ability) => $ability === 'viewAny' ? false : null);
    actAs($this->world['teacher']);

    expect(fn () => dashboard())->toThrow(AuthorizationException::class);
});

it('mounts the widget host with the owner and path for interactive embedded widgets', function (): void {
    $html = dashboard(RenderMode::Livewire);

    expect($html)->toContain('wire:id')
        ->and($html)->toContain('<data value="4">');

    $host = Livewire::test(WidgetHost::class, ['widget' => 'blueprint:dashboard', 'path' => 'dashboard.main.enrolment']);

    expect($host->get('path'))->toBe('dashboard.main.enrolment')
        ->and($host->html())->toContain('data-gcego-chart-config');

    $host->set('range', 'last_month')->assertOk();
});

it('refuses forged owners and paths', function (array $parameters): void {
    expect(statusOf(fn () => app(WidgetManager::class)->locate(...$parameters)))->toBe(404);
})->with([
    'unknown owner' => [['blueprint:nope', 'nope.main']],
    'not a widget' => [['blueprint:dashboard', 'dashboard.main']],
    'unknown path' => [['blueprint:dashboard', 'dashboard.main.secret']],
    'widget kind' => [['widget:total_students', 'total_students']],
    'malformed' => [['App\\Models\\User', 'x']],
]);

it('checks the owner requirements for embedded hosts', function (): void {
    Livewire::test(WidgetHost::class, ['widget' => 'blueprint:dashboard', 'path' => 'dashboard.main.finance.fees'])->assertForbidden();
    Livewire::test(WidgetHost::class, ['widget' => 'blueprint:dashboard', 'path' => 'dashboard.main.nope'])->assertNotFound();
});

it('maps a host-rendered embedded widget back into its owner', function (): void {
    $frame = new WidgetRenderFrame('blueprint:dashboard', new Panel, widgets()->state(new Stat('A')), null, 'h', base: 'dashboard.main.overview');

    expect($frame->path('#0'))->toBe('dashboard.main.overview')
        ->and($frame->path('#0.students'))->toBe('dashboard.main.overview.students');
});
