<?php

declare(strict_types=1);

use Gcego\Chroma\Slot;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Component;
use Gcego\Components\Properties\Property;
use Gcego\Layouts\Components\Actions;
use Gcego\Layouts\Components\Aside;
use Gcego\Layouts\Components\Card;
use Gcego\Layouts\Components\Container;
use Gcego\Layouts\Components\Divider;
use Gcego\Layouts\Components\EmptyState;
use Gcego\Layouts\Components\Footer;
use Gcego\Layouts\Components\Grid;
use Gcego\Layouts\Components\Loading;
use Gcego\Layouts\Components\Main;
use Gcego\Layouts\Components\Row;
use Gcego\Layouts\Components\ScrollArea;
use Gcego\Layouts\Components\Section;
use Gcego\Layouts\Components\Sidebar;
use Gcego\Layouts\Components\Spacer;
use Gcego\Layouts\Components\Split;
use Gcego\Layouts\Components\Stack;
use Gcego\Layouts\Components\Sticky;
use Gcego\Layouts\Configuration\Align;
use Gcego\Layouts\Configuration\Columns;
use Gcego\Layouts\Configuration\Gap;
use Gcego\Layouts\Layout;
use Gcego\Layouts\Responsive\ColumnSpan;
use Gcego\Layouts\Responsive\Visibility;
use Gcego\Runtime\Exceptions\RenderException;
use Gcego\Runtime\Rendering\RenderMode;
use Gcego\Security\Permissions\Permission;

/**
 * A plain, static child (the runtime's container renderer): <section> with a heading.
 */
function block(string $label, mixed ...$items): Component
{
    return new Component(new Property(Component::Name, strtolower(str_replace(' ', '_', $label))), new Property(Component::Label, $label), ...$items);
}

/**
 * Content the current user may not see: a layout component with a requirement nobody meets.
 */
function restricted(): Stack
{
    return new Stack(new Permission('secret.view'), block('Secret'));
}

it('applies responsive defaults to a grid without columns', function (): void {
    $html = render(new Grid(block('Students'), block('Teachers'), block('Subjects')));

    expect($html)->toContain('class="grid min-w-0 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6"')
        ->and($html)->not->toContain('<div class="min-w-0')
        ->and(substr_count($html, '<section'))->toBe(3);
});

it('renders explicit, responsive and auto-fit columns', function (): void {
    expect(render(new Grid(new Columns(2), block('A'), block('B'))))->toContain('grid-cols-1 sm:grid-cols-2 gap-6')
        ->and(render(new Grid(new Columns(['md' => 2, 'xl' => 4]), new Gap(['default' => 4, 'lg' => 8]), new Align(items: 'start'), block('A'))))->toContain('grid min-w-0 grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 lg:gap-8 items-start')
        ->and(render(new Grid(new Property(Grid::MinItemWidth, 'sm'), new Property(Grid::Dense, true), block('A'))))->toContain('grid-cols-[repeat(auto-fit,minmax(min(100%,16rem),1fr))] gap-6 grid-flow-row-dense');
});

it('wraps only children that span or hide, and nests grids', function (): void {
    $html = render(new Grid(new Columns(3),
        block('Wide', new ColumnSpan(2)),
        block('Phone only', new Visibility(hiddenFrom: 'lg')),
        new Grid(new Columns(2), block('Inner A'), block('Inner B')),
    ));

    expect($html)->toContain('<div class="min-w-0 sm:col-span-2"><section')
        ->and($html)->toContain('<div class="min-w-0 lg:hidden"><section')
        ->and(substr_count($html, 'class="grid min-w-0'))->toBe(2)
        ->and(substr_count($html, '<div class="min-w-0'))->toBe(2);
});

it('renders containers, rows and stacks', function (): void {
    expect(render(new Container(new Property(Container::Element, 'main'), block('A'))))->toStartWith('<main id=')
        ->and(render(new Container(block('A'))))->toContain('mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8')
        ->and(render(new Container(new Property(Container::Fluid, true), new Property(Container::Padding, 'none'), block('A'))))->toContain('mx-auto w-full max-w-none px-0')
        ->and(render(new Row(new Align(justify: 'between'), block('A'), block('B'))))->toContain('flex min-w-0 flex-row items-center flex-wrap gap-3 justify-between')
        ->and(render(new Row(new Property(Row::StackBelow, 'md'), new Property(Row::Wrap, false), block('A'))))->toContain('flex min-w-0 flex-col items-stretch md:flex-row md:items-center flex-nowrap gap-3')
        ->and(render(new Stack(block('A'), block('B'))))->toContain('flex min-w-0 flex-col gap-4')
        ->and(render(new Stack(new Property(Stack::Divided, true), block('A'))))->toContain('divide-y divide-gcego-border [&amp;&gt;*]:py-4');
});

it('renders sections as labelled regions with headings, actions, body and footer', function (): void {
    $html = render(new Layout('page', new Property(Layout::Label, 'Academics'),
        new Section('Academic overview', 'This term so far', new Property(Section::Icon, 'star'),
            new Actions(block('Export')),
            new Section('Results', block('Mathematics')),
            new Footer(block('Updated today')),
        ),
    ));

    expect($html)->toContain('<h1 class="text-2xl font-semibold tracking-tight text-gcego-text">Academics</h1>')
        ->and($html)->toMatch('/<section id="([^"]+)" aria-labelledby="\1-title"[^>]*>/')
        ->and($html)->toMatch('/<h2 id="[^"]+-title"[^>]*>Academic overview<\/h2> <p[^>]*>This term so far<\/p>/')
        ->and($html)->toMatch('/<h3 id="[^"]+-title"[^>]*>Results<\/h3>/')
        ->and($html)->toMatch('/<h4[^>]*>Mathematics<\/h4>/')
        ->and($html)->toContain('<svg')
        ->and($html)->toMatch('/<div class="flex shrink-0 flex-wrap items-center gap-2"><section[^>]*> <h3[^>]*>Export/')
        ->and($html)->toMatch('/border-t border-gcego-border pt-4[^"]*"><section[^>]*> <h3[^>]*>Updated today/')
        ->and(strpos($html, 'Export'))->toBeLessThan(strpos($html, 'Results'))
        ->and(strpos($html, 'Results'))->toBeLessThan(strpos($html, 'Updated today'));
});

it('hides sections whose content renders nothing, or shows their empty text', function (): void {
    $restricted = restricted();

    expect(render(new Stack(new Section('Finance', $restricted), block('Visible'))))->not->toContain('Finance')
        ->and(render(new Section('Finance', new Property(Section::Empty, 'Nothing to show yet.'), $restricted)))->toContain('<p class="py-6 text-center text-sm text-gcego-text-muted">Nothing to show yet.</p>')
        ->and(render(new Section('Empty')))->toBe('')
        ->and(render(new Grid(new Section('A', $restricted))))->toBe('');
});

it('renders cards with variants and untitled cards as plain containers', function (): void {
    expect(render(new Card('Fees', new Property(Card::Variant, 'warning'), block('A'))))->toContain('border-l-4 border-l-gcego-warning')
        ->and(render(new Card('Fees', block('A'))))->toMatch('/^<section id="[^"]+" aria-labelledby=/')
        ->and(render(new Card(block('A'))))->toMatch('/^<div id="[^"]+" class="rounded-gcego-lg border border-gcego-border bg-gcego-surface shadow-gcego-card p-5 sm:p-6/')
        ->and(render(new Card(new Property(Card::Padding, 'lg'), new Property(Card::Variant, 'elevated'), block('A'))))->toContain('shadow-lg p-6 sm:p-8');
});

it('renders splits in source order with labelled asides', function (): void {
    $html = render(new Split(new Main(block('Students')), new Aside('Recent activity', new Property(Aside::Sticky, true), block('Feed'))));
    $first = render(new Split(new Property(Split::Ratio, '1:3'), new Property(Split::StackBelow, 'md'), new Aside('Filters', block('Filter')), new Main(block('Results'))));

    expect($html)->toContain('class="grid min-w-0 grid-cols-1 lg:grid-cols-3 gap-6"')
        ->and($html)->toMatch('/<div id="[^"]+" class="min-w-0 flex flex-col gap-6 lg:col-span-2">/')
        ->and($html)->toMatch('/<aside id="[^"]+" aria-label="Recent activity" class="min-w-0 flex flex-col gap-6 lg:sticky lg:top-6 lg:self-start">/')
        ->and(strpos($html, 'Students'))->toBeLessThan(strpos($html, 'Feed'))
        ->and($first)->toContain('md:grid-cols-4')
        ->and($first)->toMatch('/<main|<div id="[^"]+" class="min-w-0 flex flex-col gap-6 md:col-span-3">/')
        ->and(strpos($first, 'Filter'))->toBeLessThan(strpos($first, 'Results'));
});

it('gives the remaining region the full width when the other renders nothing', function (): void {
    $html = render(new Split(new Main(block('Students')), new Aside('Restricted', restricted())));

    expect($html)->not->toContain('<aside')
        ->and($html)->toContain('class="grid min-w-0 grid-cols-1 gap-6"')
        ->and($html)->not->toContain('col-span');
});

it('renders sidebars with a fixed side column', function (): void {
    $html = render(new Sidebar(new Property(Sidebar::Width, 'lg'), new Property(Sidebar::Sticky, true), new Aside('Filters', block('Filter')), new Main(new Property(Main::Element, 'section'), new Property(Main::Label, 'Results'), block('Rows'))));

    expect($html)->toContain('flex min-w-0 flex-col lg:flex-row gap-6')
        ->and($html)->toMatch('/<aside id="[^"]+" aria-label="Filters" class="min-w-0 flex flex-col gap-6 shrink-0 lg:w-80 lg:sticky lg:top-6 lg:self-start">/')
        ->and($html)->toMatch('/<section id="[^"]+" aria-label="Results" class="min-w-0 flex flex-col gap-6 flex-1">/');
});

it('renders dividers, spacers, scroll areas, sticky regions, empty and loading states', function (): void {
    expect(render(new Divider))->toBe('<div aria-hidden="true" class="border-0 border-t border-gcego-border my-4"></div>')
        ->and(render(new Divider(new Property(Divider::Decorative, false), new Property(Divider::Spacing, 'lg'))))->toBe('<hr class="border-0 border-t border-gcego-border my-8">')
        ->and(render(new Divider('Or')))->toContain('>Or</div>')
        ->and(render(new Divider('Or', new Property(Divider::Decorative, false))))->toContain('role="separator" aria-label="Or"')
        ->and(render(new Divider(new Property(Divider::Orientation, 'vertical'))))->toContain('w-px self-stretch bg-gcego-border mx-4')
        ->and(render(new Spacer(['default' => 4, 'lg' => 12])))->toBe('<div aria-hidden="true" class="shrink-0 basis-4 lg:basis-12"></div>')
        ->and(render(new Spacer(new Property(Spacer::Grow, true))))->toBe('<div aria-hidden="true" class="flex-1"></div>')
        ->and(render(new ScrollArea('Audit log', new Property(ScrollArea::MaxHeight, 'lg'), block('A'))))->toMatch('/role="region" aria-label="Audit log" tabindex="0" class="[^"]*focus-visible:outline-2[^"]*overflow-y-auto overflow-x-hidden max-h-\[32rem\]"/')
        ->and(render(new ScrollArea(block('A'))))->toContain('aria-label="Scrollable content"')
        ->and(render(new Sticky(new Property(Sticky::Offset, 16), new Property(Sticky::From, 'lg'), new Property(Sticky::Layer, 'raised'), block('A'))))->toContain('class="lg:sticky top-16 z-20 bg-gcego-surface/95')
        ->and(render(new Sticky(new Property(Sticky::Position, 'bottom'), new Property(Sticky::Surface, false), block('A'))))->toContain('class="sticky bottom-0 z-10"')
        ->and(render(new EmptyState('No students yet', 'Import a class list.', new Property(EmptyState::Icon, 'users'), block('Import'))))->toMatch('/<p class="text-sm font-semibold text-gcego-text">No students yet<\/p> <p[^>]*>Import a class list.<\/p> <div class="mt-2 flex/')
        ->and(render(new Loading))->toMatch('/role="status" aria-live="polite" aria-busy="true" class="flex flex-col gap-3"> <span class="sr-only">Loading…<\/span>( <div aria-hidden="true" class="rounded-gcego bg-gcego-surface-muted motion-safe:animate-pulse h-4 w-[^"]+"><\/div>){3}/')
        ->and(substr_count(render(new Loading(new Property(Loading::Variant, 'grid'))), 'h-28'))->toBe(3);
});

it('merges Tailwind slot overrides over computed classes', function (): void {
    $html = render(new Section('Academic overview', block('A'), new Tailwind(
        new Slot(Tailwind::Wrapper, ['rounded-2xl', 'border', 'border-slate-200', 'bg-white', 'p-6', 'shadow-sm']),
        new Slot(Tailwind::Body, ['gap-8']),
    )));

    // p-6 replaces the computed base padding; the computed sm:p-6 stays (variants merge separately).
    expect($html)->toContain('class="rounded-2xl border border-slate-200 bg-white shadow-sm p-6 sm:p-6 flex min-w-0 flex-col gap-4"')
        ->and($html)->toContain('class="flex min-w-0 flex-col gap-8"')
        ->and(render(new Grid(block('A'), new Tailwind(new Slot(Tailwind::Wrapper, ['gap-10', 'md:grid-cols-5'])))))->toContain('class="grid min-w-0 grid-cols-1 gap-10 md:grid-cols-5"');
});

it('refuses styling slots a component does not have', function (): void {
    expect(fn () => render(new Grid(block('A'), new Tailwind(new Slot(Tailwind::Header, ['p-4'])))))->toThrow(RenderException::class);
});

it('escapes every text it prints', function (): void {
    $html = render(new Section('<script>alert(1)</script>', '"><img src=x onerror=alert(1)>', new Property(Section::Empty, '<b>none</b>')));

    expect($html)->not->toContain('<script>')
        ->and($html)->not->toContain('<img')
        ->and($html)->not->toContain('<b>')
        ->and($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});

it('adds stable morph keys in Livewire renders only', function (): void {
    $layout = new Grid(new Columns(3), block('A', new ColumnSpan(2)), block('B'));
    $live = render($layout, RenderMode::Livewire);

    expect(render($layout))->not->toContain('wire:key')
        ->and($live)->toMatch('/<div id="([^"]+)" class="[^"]+" wire:key="\1">/')
        ->and($live)->toMatch('/<div class="min-w-0 sm:col-span-2" wire:key="[^"]+-item">/')
        ->and(render($layout, RenderMode::Livewire))->toBe($live);
});

it('lets applications override views', function (): void {
    view()->prependNamespace('gcego-layouts', dirname(__DIR__).'/Fixtures/views');

    expect(render(new Spacer))->toBe('<hr data-overridden-spacer>');
});
