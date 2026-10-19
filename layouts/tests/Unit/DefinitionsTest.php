<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\Property;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Forms\Form;
use Gcego\Infolists\Infolist;
use Gcego\Layouts\Components\Accordion;
use Gcego\Layouts\Components\AccordionItem;
use Gcego\Layouts\Components\Aside;
use Gcego\Layouts\Components\Container;
use Gcego\Layouts\Components\Divider;
use Gcego\Layouts\Components\Grid;
use Gcego\Layouts\Components\Main;
use Gcego\Layouts\Components\Section;
use Gcego\Layouts\Components\Spacer;
use Gcego\Layouts\Components\Split;
use Gcego\Layouts\Components\Stack;
use Gcego\Layouts\Components\Tab;
use Gcego\Layouts\Components\Tabs;
use Gcego\Layouts\Configuration\Columns;
use Gcego\Layouts\Layout;
use Gcego\Layouts\Registry\LayoutKind;
use Gcego\Layouts\Responsive\ColumnSpan;
use Gcego\Layouts\Tests\Fixtures\Definitions;
use Gcego\Registry\Exceptions\InvalidDefinitionException;
use Gcego\Registry\Registry;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Table;
use Gcego\Widgets\Statistics\Stat;

function errorsOf(Component $component): array
{
    return array_map(strval(...), $component->validate()->errors());
}

it('defines a minimal grid of widgets without touching the database', function (): void {
    $queries = queriesDuring(function (): void {
        $this->grid = new Grid(new Stat('Students', 1), new Stat('Teachers', 2), new Stat('Subjects', 3));
        Definitions::dashboard();
        Definitions::students();
    });

    expect($queries)->toBe([])
        ->and($this->grid->children())->toHaveCount(3)
        ->and($this->grid->columns(3)->classes())->toBe('grid-cols-1 sm:grid-cols-2 lg:grid-cols-3')
        ->and(errorsOf($this->grid))->toBe([]);
});

it('supports shorthand and property syntax', function (): void {
    $section = new Section('Academic overview', 'This term', new Grid(new Columns(3), new Stat('Students', 1)));
    $explicit = new Section(new Property(Section::Label, 'Academic overview'), new Property(Section::Description, 'This term'));
    $container = new Container(new Property(Container::MaxWidth, '5xl'), new Property(Container::Element, 'main'));

    expect($section->title())->toBe('Academic overview')
        ->and($section->description())->toBe('This term')
        ->and($explicit->title())->toBe($section->title())
        ->and(errorsOf($container))->toBe([])
        ->and((new Layout('dashboard'))->layoutName())->toBe('dashboard')
        ->and((new Layout(new Property(Layout::Label, 'School dashboard')))->layoutName())->toBe('school_dashboard')
        ->and((new Tab('Exam results'))->tabName())->toBe('exam-results')
        ->and((new Spacer(8))->size()->values)->toBe(['default' => 8]);
});

it('rejects invalid property values', function (Component $component, string $message): void {
    expect(implode(' ', errorsOf($component)))->toContain($message);
})->with([
    'max width' => [fn () => new Container(new Property(Container::MaxWidth, 'huge')), 'max_width'],
    'element' => [fn () => new Container(new Property(Container::Element, 'script')), 'element'],
    'spacer' => [fn () => new Spacer(7), 'a spacing step'],
    'layout name' => [fn () => new Layout('Dashboard Page'), 'a lowercase name'],
    'icon' => [fn () => new Section('A', new Property(Section::Icon, '../../etc')), 'an icon name'],
]);

it('is immutable: configuration nodes are read-only and validation is repeatable', function (): void {
    $grid = new Grid(new Columns(3), new Stat('A', 1));
    $columns = $grid->configurations()->first(Columns::class);

    expect(fn () => $columns->columns = $columns->columns)->toThrow(Error::class)
        ->and($grid->normalize())->toBe($grid->normalize())
        ->and(errorsOf($grid))->toBe(errorsOf($grid));
});

it('keeps stable paths and tab identities', function (): void {
    $paths = static fn (): array => array_map(static fn ($node): string => $node->path->toString(), iterator_to_array((new ComponentWalker)->preOrder(Definitions::dashboard()), false));

    expect($paths())->toBe($paths())
        ->and($paths())->toContain('dashboard.overview.stats.students', 'dashboard.more.#0');
});

it('validates structure', function (Component $component, string $message): void {
    expect(implode(' ', errorsOf($component)))->toContain($message);
})->with([
    'split needs two regions' => [fn () => new Split(new Main(new Stat('A', 1))), '"layouts.split" has too few children'],
    'split needs one of each' => [fn () => new Split(new Main(new Stat('A', 1)), new Main(new Stat('B', 1))), 'exactly one Main and one Aside'],
    'tabs hold tabs' => [fn () => new Tabs(new Main(new Stat('A', 1))), '"layouts.tabs" does not accept a child of type "layouts.main"'],
    'accordion holds items' => [fn () => new Accordion(new Tab('A')), '"layouts.accordion" does not accept a child of type "layouts.tab"'],
    'divider has no children' => [fn () => new Divider(new Stat('A', 1)), '"layouts.divider" does not accept child components'],
    'duplicate tabs' => [fn () => new Tabs(new Tab('Results'), new Tab('Results')), 'two tabs are named "results"'],
    'unknown active tab' => [fn () => new Tabs(new Property(Tabs::Active, 'nope'), new Tab('Results')), 'Active is "nope"'],
    'span wider than the grid' => [fn () => new Grid(new Columns(2), new Stat('A', 1, new ColumnSpan(4))), 'spans 4 columns, but the grid has at most 2'],
    'misplaced main' => [fn () => new Layout('x', new Stack(new Main(new Stat('A', 1)))), 'Main at "x.#0.#0" must be a direct child of Split or Sidebar'],
    'misplaced tab' => [fn () => new Layout('x', new Tab('Loose')), 'Tab at "x.#0" must be a direct child of Tabs'],
    'misplaced item' => [fn () => new Layout('x', new AccordionItem('Loose')), 'AccordionItem at "x.#0" must be a direct child of Accordion'],
]);

it('lets any component carry placement configuration', function (): void {
    $stat = new Stat('Revenue', 1, new ColumnSpan(2));

    expect(errorsOf($stat))->toBe([])
        ->and($stat->configurations()->first(ColumnSpan::class))->toBeInstanceOf(ColumnSpan::class)
        ->and(Grid::spanOf($stat)?->span->values)->toBe(['default' => 2])
        ->and(Grid::spanOf(new Stat('Wide', 1, new Property(Stat::Span, 'full')))?->span->values)->toBe(['default' => 'full'])
        ->and(Grid::spanOf(new Stat('Plain', 1)))->toBeNull();
});

it('keeps blueprint parts nested in layouts discoverable', function (): void {
    $students = Definitions::students();

    expect($students->part('table'))->toBeInstanceOf(Table::class)
        ->and($students->part('form'))->toBeInstanceOf(Form::class)
        ->and($students->part('infolist'))->toBeInstanceOf(Infolist::class)
        ->and($students->parts('action'))->toHaveCount(1)
        ->and($students->composition()->issues())->toBe([]);
});

it('registers layouts as the "layout" kind and refuses parts that need a blueprint', function (): void {
    $kind = new LayoutKind;
    $layout = new Layout('reports', new Section('Report', new Table(new Text('name', 'Name'))));

    expect($kind->name(Definitions::dashboard()))->toBe('dashboard')
        ->and($kind->validate(Definitions::dashboard()))->toBe([])
        ->and($kind->metadata(Definitions::dashboard()))->toMatchArray(['title' => 'Dashboard'])
        ->and(implode(' ', $kind->validate($layout)))->toContain('Table at "reports.#0.table" needs a blueprint')
        ->and(fn () => app(Registry::class)->register($layout))->toThrow(InvalidDefinitionException::class)
        ->and(implode(' ', $kind->validate(new Layout(new Section('Untitled')))))->toContain('needs a Name');
});

it('accepts layouts as blueprint parts', function (): void {
    expect((new Split(new Main, new Aside))->blueprintRole()->name)->toBe('layout')
        ->and((new Split(new Main, new Aside))->blueprintRole()->multiple)->toBeTrue();
});
