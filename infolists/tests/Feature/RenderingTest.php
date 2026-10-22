<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\Slot;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Properties\Property;
use Gcego\Data\Source;
use Gcego\Infolists\Entries\Text;
use Gcego\Infolists\Infolist;
use Gcego\Infolists\Layout\Section;
use Gcego\Infolists\Tests\Fixtures\Blueprints;
use Gcego\Infolists\Tests\Fixtures\Models\Subject;
use Gcego\Registry\Registry;
use Gcego\Rendering\Context\RenderMode;
use Gcego\Rendering\Exceptions\RenderException;
use Gcego\Rendering\Facades\Rendering;
use Gcego\Security\Requirements\Ability;
use Illuminate\Support\Facades\Blade;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['editor']);
});

it('renders the minimal example as semantic label/value pairs', function (): void {
    $html = details('minimal', $this->world['subject']->id);

    expect($html)->toContain('<article')
        ->and($html)->toContain('<dl class="grid')
        ->and($html)->toContain('<dt')
        ->and($html)->toContain('Subject Name</dt>')
        ->and($html)->toContain('<dd')
        ->and($html)->toContain('Algebra')
        ->and($html)->toContain('data-gcego-badge="neutral"')
        ->and($html)->toContain('<time datetime=');
});

it('renders sections, grids, tabs and split layouts accessibly', function (): void {
    $html = details('subjects', $this->world['subject']->id);

    expect($html)->toContain('<section')
        ->and($html)->toContain('aria-labelledby=')
        ->and($html)->toContain('Subject Information</h3>')
        ->and($html)->toContain('role="tablist"')
        ->and(substr_count($html, 'role="tab"'))->toBe(2)
        ->and($html)->toContain('role="tabpanel"')
        ->and($html)->toContain('aria-controls=')
        ->and($html)->toContain('role="complementary"')
        ->and($html)->toContain('aria-expanded="true"')
        ->and($html)->toContain('sm:grid-cols-2')
        ->and($html)->toContain('lg:grid-cols-[minmax(0,1fr)_20rem]')
        ->and($html)->toContain('sm:col-span-2');
});

it('formats values in place: markdown, multiline, money, badges, links, lists', function (): void {
    $html = details('subjects', $this->world['subject']->id);

    expect($html)->toContain('<strong>equations</strong>')
        ->and($html)->toContain('whitespace-pre-line')
        ->and($html)->toMatch('/class="whitespace-pre-line"[^>]*>\S/')   // no leading newline shown as a blank line
        ->and($html)->toContain('FCFA')
        ->and($html)->toContain('data-gcego-badge="success"')
        ->and($html)->toContain('href="https://gcego.test/algebra"')
        ->and($html)->toContain('rel="noopener noreferrer"')
        ->and($html)->toContain('href="mailto:teacher@gcego.test"')
        ->and($html)->toContain('loading="lazy"')
        ->and($html)->toContain('English')
        ->and($html)->toContain('High priority')
        ->and($html)->toContain('data-copy="Algebra"');
});

it('hides empty optional entries and whole layouts with nothing to show', function (): void {
    $this->world['subject']->update(['notes' => null]);

    expect(details('subjects', $this->world['subject']->id))->not->toContain('data-gcego-entry="notes"');

    app(Registry::class)->register(new Blueprint('sparse', new Ability('viewAny', Subject::class), new Source(Subject::class), new Infolist(
        new Section('Empty', new Text('archive_reason', new Property(Text::HiddenWhenEmpty, true))),
    )));

    $html = details('sparse', $this->world['subject']->id);

    expect($html)->not->toContain('<section')
        ->and($html)->toContain('No information to show.');
});

it('applies styling slots and instance Tailwind overrides', function (): void {
    $infolist = new Infolist(new Text('name'), new Tailwind(new Slot(Tailwind::Wrapper, ['rounded-2xl', 'border-slate-200']), new Slot(Tailwind::Body, ['p-6', 'space-y-6'])));
    $styling = app(ChromaManager::class);

    expect($styling->classes($infolist, Tailwind::Wrapper))->toContain('rounded-2xl')
        ->and($styling->classes($infolist, Tailwind::Body))->toContain('p-6')
        ->and($styling->classes(new Text('name'), Tailwind::Label))->toContain('text-gcego-text-muted')
        ->and(details('minimal', $this->world['subject']->id))->toContain('rounded-gcego-lg');
});

it('renders statically inside a blueprint render and mounts Livewire in Livewire mode', function (): void {
    $static = Rendering::render(Blueprints::minimal(), identity: 'blueprint:minimal', metadata: ['record' => $this->world['subject']->id])->toHtml();
    $live = Rendering::render(Blueprints::minimal(), RenderMode::Livewire, identity: 'blueprint:minimal', metadata: ['record' => $this->world['subject']->id])->toHtml();
    $none = Rendering::render(Blueprints::minimal(), identity: 'blueprint:minimal')->toHtml();

    expect($static)->toContain('Algebra')
        ->and($static)->not->toContain('wire:id')
        ->and($live)->toContain('wire:name="gcego-infolist"')
        ->and($live)->toContain('Algebra')
        ->and($none)->not->toContain('Algebra');
});

it('refuses to render a bare infolist without a trusted record', function (): void {
    expect(fn () => Rendering::render(new Infolist(new Text('name')))->toHtml())->toThrow(RenderException::class, 'renders a record of a registered blueprint');
});

it('renders through the anonymous Blade component without Livewire', function (): void {
    $html = Blade::render('<x-gcego-infolists::infolist definition="minimal" :record="$id" />', ['id' => $this->world['subject']->id]);

    expect($html)->toContain('Algebra')
        ->and($html)->not->toContain('wire:id');
});

it('renders a large schema with nested repeatables within a bounded query count', function (): void {
    for ($i = 0; $i < 60; $i++) {
        $this->world['subject']->topics()->create(['title' => 'Topic '.$i, 'teacher_id' => $this->world['teachers']['ada']->id, 'position' => $i + 10]);
    }

    $started = hrtime(true);
    $queries = queriesDuring(fn () => details('subjects', $this->world['subject']->id));
    $ms = (hrtime(true) - $started) / 1e6;

    fwrite(STDERR, sprintf("\n[infolists perf] full subjects infolist with 62 repeatable items: %.1f ms, %d queries\n", $ms, count($queries)));

    expect(count($queries))->toBeLessThanOrEqual(6)
        ->and($ms)->toBeLessThan(3000.0);
});
