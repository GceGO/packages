<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\Properties\Property;
use Gcego\Layouts\Components\Accordion;
use Gcego\Layouts\Components\AccordionItem;
use Gcego\Layouts\Components\Collapsible;
use Gcego\Layouts\Components\Section;
use Gcego\Layouts\Components\Stack;
use Gcego\Layouts\Components\Tab;
use Gcego\Layouts\Components\Tabs;
use Gcego\Rendering\Context\RenderMode;
use Gcego\Security\Permissions\Permission;
use Illuminate\Support\Facades\Process;

function panel(string $text): Component
{
    return new Component(new Property(Component::Label, $text));
}

function tabs(mixed ...$items): Tabs
{
    return new Tabs(new Property(Tabs::Label, 'Student'), ...($items ?: [new Tab('Profile', panel('Profile details')), new Tab('Results', new Property(Tab::Icon, 'star'), panel('Exam results')), new Tab('Notes', panel('Teacher notes'))]));
}

/**
 * Runs the rendered Alpine state of a tab set in Node and reports what each keyboard handler does.
 *
 * @return array{steps: list<string>, focused: list<string>}
 */
function driveTabs(string $html): array
{
    preg_match('/x-data="([^"]+)"/', $html, $match);
    $script = <<<'JS'
        const data = eval('(' + process.env.XDATA + ')');
        const focused = [];
        data.$refs = new Proxy({}, { get: (_, key) => ({ focus: () => focused.push(key) }) });
        data.$nextTick = (callback) => callback();
        const steps = [];
        for (const action of [() => data.move(1), () => data.move(1), () => data.move(1), () => data.move(-1), () => data.focus(-1), () => data.focus(0), () => data.select('notes'), () => data.focus(9)]) {
            action();
            steps.push(data.active);
        }
        console.log(JSON.stringify({ steps, focused }));
        JS;
    $result = Process::env(['XDATA' => html_entity_decode($match[1] ?? '', ENT_QUOTES)])->run(['node', '-e', $script]);

    return json_decode($result->throw()->output(), true);
}

it('renders WAI-ARIA tabs with every panel on the server and the first one shown', function (): void {
    $html = render(tabs());

    preg_match_all('/<button type="button" role="tab" id="([^"]+)" aria-controls="([^"]+)" aria-selected="(true|false)" tabindex="(0|-1)"/', $html, $tabs, PREG_SET_ORDER);
    preg_match_all('/<div role="tabpanel" id="([^"]+)" aria-labelledby="([^"]+)" tabindex="0"( hidden)?/', $html, $panels, PREG_SET_ORDER);

    expect($html)->toContain('role="tablist" aria-orientation="horizontal" aria-label="Student"')
        ->and($tabs)->toHaveCount(3)
        ->and($panels)->toHaveCount(3)
        ->and(array_column($tabs, 3))->toBe(['true', 'false', 'false'])
        ->and(array_column($tabs, 4))->toBe(['0', '-1', '-1'])
        ->and(array_column($tabs, 2))->toBe(array_column($panels, 1))
        ->and(array_column($tabs, 1))->toBe(array_column($panels, 2))
        ->and(array_map(static fn (array $panel): bool => isset($panel[3]), $panels))->toBe([false, true, true])
        ->and($html)->toContain('Exam results')
        ->and($html)->toContain('x-on:keydown.arrow-right.prevent="move(1)" x-on:keydown.arrow-left.prevent="move(-1)" x-on:keydown.home.prevent="focus(0)" x-on:keydown.end.prevent="focus(-1)"')
        ->and($html)->toContain('focus-visible:outline-2')
        ->and($html)->toContain('overflow-x-auto')
        ->and(render(tabs()))->toBe($html);
});

it('switches tabs with arrow keys, Home and End, wrapping around', function (): void {
    $result = driveTabs(render(tabs()));

    expect($result['steps'])->toBe(['results', 'notes', 'profile', 'notes', 'notes', 'profile', 'notes', 'notes'])
        ->and($result['focused'])->toBe(['tab-results', 'tab-notes', 'tab-profile', 'tab-notes', 'tab-notes', 'tab-profile']);
})->skip(fn (): bool => Process::run(['node', '--version'])->failed(), 'Node.js is not installed.');

it('opens the Active tab and leaves out tabs whose content may not be seen', function (): void {
    $active = render(tabs(new Property(Tabs::Active, 'results'), new Tab('Profile', panel('Profile details')), new Tab('Results', panel('Exam results'))));
    $restricted = render(tabs(new Property(Tabs::Active, 'fees'), new Tab('Fees', new Stack(new Permission('fees.view'), panel('Balances'))), new Tab('Profile', panel('Profile details')), new Tab('Hidden', new Permission('secret.view'), panel('Secret'))));

    expect($active)->toMatch('/aria-selected="false" tabindex="-1"[^>]*>\s*<span>Profile/')
        ->and($active)->toMatch('/aria-selected="true" tabindex="0"[^>]*>\s*<span>Results/')
        ->and($restricted)->not->toContain('Fees')
        ->and($restricted)->not->toContain('Secret')
        ->and($restricted)->not->toContain('Hidden')
        ->and($restricted)->toMatch('/aria-selected="true" tabindex="0"[^>]*>\s*<span>Profile/')
        ->and(render(tabs(new Tab('Fees', new Stack(new Permission('fees.view'), panel('Balances'))))))->toBe('');
});

it('keeps the browser-side state across Livewire updates', function (): void {
    $html = render(tabs(), RenderMode::Livewire);

    expect(substr_count($html, 'wire:ignore.self'))->toBe(6)
        ->and(render(tabs()))->not->toContain('wire:ignore');
});

it('renders accordions as native disclosures', function (): void {
    $html = render(new Accordion(
        new AccordionItem('Fees', 'Balances and receipts', panel('Paid in full')),
        new AccordionItem('Transport', new Property(AccordionItem::Open, true), new Property(AccordionItem::Icon, 'star'), panel('Bus 4')),
        new AccordionItem('Restricted', new Permission('secret.view'), panel('Secret')),
    ));
    $exclusive = render(new Accordion(new Property(Accordion::Multiple, false), new AccordionItem('A', panel('a')), new AccordionItem('B', panel('b'))));

    expect(substr_count($html, '<details'))->toBe(2)
        ->and(substr_count($html, '<summary'))->toBe(2)
        ->and($html)->toMatch('/<details id="[^"]+" class="group" >\s*<summary[^>]*>.*Fees.*Balances and receipts/s')
        ->and($html)->toMatch('/<details id="[^"]+" class="group" open\s*>/')
        ->and($html)->not->toContain('Secret')
        ->and($html)->toContain('[&amp;::-webkit-details-marker]:hidden')
        ->and($html)->toContain('focus-visible:outline-2')
        ->and(preg_match_all('/name="([^"]+-group)"/', $exclusive, $groups))->toBe(2)
        ->and(array_unique($groups[1]))->toHaveCount(1)
        ->and($html)->not->toContain(' name="');
});

it('renders collapsible regions and sections, closed or open', function (): void {
    $closed = render(new Collapsible('Advanced', 'Rarely needed', panel('Settings')));
    $open = render(new Collapsible('Notes', new Property(Collapsible::Open, true), panel('Text')));
    $section = render(new Section('Archive', new Property(Section::Collapsible, true), new Property(Section::Collapsed, true), panel('Old terms')));

    expect($closed)->toMatch('/^<details id="[^"]+" class="group min-w-0"\s*>/')
        ->and($closed)->toContain('motion-reduce:transition-none')
        ->and($open)->toMatch('/^<details id="[^"]+" class="group min-w-0" open\s*>/')
        ->and($section)->toMatch('/^<details id="[^"]+" class="[^"]+ group"\s*>\s*<summary[^>]*>\s*<span[^>]*>\s*<span[^>]*>\s*<span class="block min-w-0">\s*<h1 id="[^"]+-title"[^>]*>Archive<\/h1>/')
        ->and($section)->toContain('Old terms')
        ->and(render(new Section('Archive', new Property(Section::Collapsible, true), panel('x'))))->toMatch('/^<details[^>]* open\s*>/');
});
