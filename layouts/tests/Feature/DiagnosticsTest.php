<?php

declare(strict_types=1);

use Gcego\Chroma\Slot;
use Gcego\Chroma\Slots\SlotRegistry;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Component;
use Gcego\Components\Properties\Property;
use Gcego\Layouts\Components\Aside;
use Gcego\Layouts\Components\Divider;
use Gcego\Layouts\Components\Grid;
use Gcego\Layouts\Components\Main;
use Gcego\Layouts\Components\ScrollArea;
use Gcego\Layouts\Components\Section;
use Gcego\Layouts\Components\Split;
use Gcego\Layouts\Components\Stack;
use Gcego\Layouts\Components\Tab;
use Gcego\Layouts\Components\Tabs;
use Gcego\Layouts\Configuration\Columns;
use Gcego\Layouts\Diagnostics\LayoutDiagnostic;
use Gcego\Layouts\Diagnostics\LayoutInspector;
use Gcego\Layouts\Exceptions\InvalidLayoutException;
use Gcego\Layouts\Layout;
use Gcego\Layouts\LayoutsConfig;
use Gcego\Layouts\LayoutsServiceProvider;
use Gcego\Layouts\Responsive\ColumnSpan;
use Gcego\Layouts\Tests\Fixtures\Models\Student;
use Gcego\Layouts\Tests\Fixtures\Unrenderable;
use Gcego\Rendering\Renderers\RendererRegistry;
use Gcego\Security\Requirements\Ability;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Table;
use Gcego\Widgets\Statistics\Stat;

it('inspects a registered layout without querying, rendering or authorizing', function (): void {
    $queries = queriesDuring(fn () => $this->report = layouts()->inspect('dashboard'));

    expect($queries)->toBe([])
        ->and($this->report->identity)->toBe('layout:dashboard')
        ->and($this->report->isHealthy())->toBeTrue()
        ->and($this->report->nodes[0])->toMatchArray(['path' => 'dashboard', 'type' => 'layouts.layout', 'depth' => 0])
        ->and(array_column($this->report->nodes, 'path'))->toContain('dashboard.overview.stats.boys')
        ->and($this->report->nodes[0]['slots'])->toBe(['root', 'header', 'title', 'description'])
        ->and(json_encode($this->report->toArray()))->not->toContain('1200');
});

it('reports what is wrong with a layout, by check', function (): void {
    $report = layouts()->inspect(new Layout('broken',
        new Section('Report', new Table(new Text('name', 'Name'))),
        new Stack(new Stat('Loose span', 1, new ColumnSpan(2)), new Divider(new Property(Divider::Orientation, 'vertical'))),
        new Grid(new Columns(2), new Stat('Too wide', 1, new ColumnSpan(4))),
        new Grid(new Property(Grid::Dense, true), new Property(Grid::MinItemWidth, 'sm'), new Stat('Fit', 1, new ColumnSpan(2))),
        new ScrollArea(new Stat('Log', 1)),
        new Tabs(new Tab('Only')),
        new Split(new Main(new Property(Main::Element, 'main'), new Stat('A', 1)), new Aside(new Stat('B', 1))),
        new Split(new Main(new Property(Main::Element, 'main'), new Stat('C', 1)), new Aside(new Stat('D', 1))),
        new Stack(new Component(new Property(Component::Name, 'same')), new Component(new Property(Component::Name, 'same'))),
        new Section('Styled', new Stat('E', 1), new Tailwind(new Slot('tablist', ['p-4']))),
        new Stack(new Unrenderable),
    ));

    expect($report->isHealthy())->toBeFalse()
        ->and($report->checks())->toContain('schema', 'authorization', 'nesting', 'spans', 'renderer', 'styling', 'accessibility', 'identity')
        ->and(collect($report->errors())->pluck('check')->unique()->values()->all())->toContain('schema', 'renderer', 'styling', 'accessibility')
        ->and(collect($report->diagnostics)->firstWhere('check', 'nesting')?->message)->toContain('Table at "broken.#0.table" needs a blueprint')
        ->and(collect($report->warnings())->pluck('message')->implode(' '))->toContain('ignored here', 'ignores spans', 'Label', 'single tab', 'source order', 'vertical divider', 'positions')
        ->and(collect($report->errors())->pluck('message')->implode(' '))->toContain('spans 4 columns', 'one main landmark', 'Slot "tablist"', 'No renderer');
});

it('reports misplaced regions outside a Layout, unknown identities and oversized trees', function (): void {
    config()->set('gcego-layouts.diagnostics.max_nodes', 5);
    app()->forgetInstance(LayoutsConfig::class);
    app()->forgetInstance(LayoutInspector::class);
    $stack = new Stack(new Main(new Stat('A', 1)), new Stat('B', 1), new Stat('C', 1), new Stat('D', 1), new Stat('E', 1));

    expect(collect(app(LayoutInspector::class)->inspect($stack)->diagnostics)->map(fn (LayoutDiagnostic $diagnostic): string => $diagnostic->check)->all())->toContain('nesting', 'performance')
        ->and(layouts()->inspect('nope')->checks())->toBe(['registry'])
        ->and(layouts()->inspect('nope')->isHealthy())->toBeFalse();
});

it('registers the package, its renderer, its slots and its kind without queries', function (): void {
    $queries = queriesDuring(function (): void {
        (new LayoutsServiceProvider(app()))->register();
    });

    expect($queries)->toBe([])
        ->and(app(Gcego\Core\Gcego::class)->hasPackage('gcego/layouts'))->toBeTrue()
        ->and(config('gcego-layouts.grid.default_gap'))->toBe(6);

    foreach (LayoutsServiceProvider::Components as $component) {
        expect(app(RendererRegistry::class)->has($component))->toBeTrue()
            ->and(array_keys(app(SlotRegistry::class)->for($component) ?? []))->toBe(array_keys($component::styleSlots()));
    }
});

it('fails fast on invalid configuration', function (): void {
    config()->set('gcego-layouts.grid.default_gap', 7);
    app()->forgetInstance(LayoutsConfig::class);

    expect(fn () => app(LayoutsConfig::class))->toThrow(InvalidLayoutException::class, 'default_gap');
});

it('describes a widget-only definition as healthy', function (): void {
    expect(layouts()->inspect(new Layout('ok', new Ability('viewAny', Student::class), new Grid(new Stat('A', 1))))->isHealthy())->toBeTrue();
});
