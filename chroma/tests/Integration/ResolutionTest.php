<?php

declare(strict_types=1);

use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\Exceptions\InvalidSlotException;
use Gcego\Chroma\Exceptions\InvalidThemeException;
use Gcego\Chroma\Slot;
use Gcego\Chroma\Slots\SlotRegistry;
use Gcego\Chroma\Slots\SlotResolver;
use Gcego\Chroma\Tailwind;
use Gcego\Chroma\Tests\Fixtures\ExampleCard;
use Gcego\Chroma\Themes\Theme;
use Gcego\Components\Component;

it('gives components professional defaults with no styling code', function (): void {
    $styling = app(ChromaManager::class);
    $card = new ExampleCard('Subjects');

    expect($styling->classes($card, Tailwind::Wrapper))->toBe('rounded-gcego-lg border border-gcego-border bg-gcego-surface text-gcego-text shadow-gcego-card')
        ->and($styling->classes($card, Tailwind::Header))->toBe('flex flex-wrap items-start justify-between gap-4 border-b border-gcego-border px-6 py-4')
        ->and($styling->classes($card, Tailwind::Body))->toBe('p-6');
});

it('applies all five layers in documented precedence', function (): void {
    $styling = app(ChromaManager::class);
    $styling->registerTheme(new Theme('brand', presets: [new Slot('surface.card', ['rounded-3xl', 'shadow-lg'])]));
    $styling->override(ExampleCard::class, new Slot(Tailwind::Wrapper, ['shadow-none', 'p-2']));

    $card = new ExampleCard('Subjects', new Tailwind('p-8'));

    // 1 preset surface.card -> 2 theme: rounded-3xl, shadow-lg -> 3 component: none -> 4 app: shadow-none p-2 -> 5 instance: p-8
    expect($styling->classes($card, Tailwind::Wrapper, 'brand'))->toBe('rounded-3xl border border-gcego-border bg-gcego-surface text-gcego-text shadow-none p-8')
        ->and($styling->classes($card, Tailwind::Wrapper))->toBe('rounded-gcego-lg border border-gcego-border bg-gcego-surface text-gcego-text shadow-none p-8');
});

it('supports the documented developer levels', function (): void {
    $styling = app(ChromaManager::class);

    $shorthand = new ExampleCard('A', new Tailwind('rounded-2xl', 'border', 'bg-white', 'shadow-sm'));
    $slots = new ExampleCard('B', new Tailwind(
        new Slot(Tailwind::Wrapper, [
            'rounded-2xl',
            'border-slate-200',
            'bg-white',
        ]),
        new Slot(Tailwind::Header, [
            'px-8',
        ]),
        new Slot(Tailwind::Body, [
            'p-8',
        ]),
    ));

    expect($styling->classes($shorthand, Tailwind::Wrapper))->toBe('rounded-2xl border border-gcego-border bg-white text-gcego-text shadow-sm')
        ->and($styling->classes($slots, Tailwind::Wrapper))->toBe('rounded-2xl border border-slate-200 bg-white text-gcego-text shadow-gcego-card')
        ->and($styling->classes($slots, Tailwind::Header))->toBe('flex flex-wrap items-start justify-between gap-4 border-b border-gcego-border px-8 py-4')
        ->and($styling->classes($slots, Tailwind::Body))->toBe('p-8');
});

it('supports replace and remove at instance level', function (): void {
    $styling = app(ChromaManager::class);
    $card = new ExampleCard('A', new Tailwind(
        Slot::replace(Tailwind::Header, ['flex', 'gap-2']),
        Slot::remove(Tailwind::Wrapper, ['shadow-gcego-card', 'border']),
    ));

    expect($styling->classes($card, Tailwind::Header))->toBe('flex gap-2')
        ->and($styling->classes($card, Tailwind::Wrapper))->toBe('rounded-gcego-lg border-gcego-border bg-gcego-surface text-gcego-text');
});

it('preserves responsive, state and dark variants', function (): void {
    $card = new ExampleCard('A', new Tailwind(new Slot(Tailwind::Body, ['grid', 'grid-cols-1', 'gap-4', 'md:grid-cols-2', 'xl:grid-cols-3', 'hover:bg-gcego-surface-muted', 'dark:shadow-none', '@container'])));

    expect(app(ChromaManager::class)->classes($card, Tailwind::Body))->toBe('p-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3 hover:bg-gcego-surface-muted dark:shadow-none @container');
});

it('rejects unknown slots in strict mode', function (): void {
    $styling = app(ChromaManager::class);

    expect(fn () => $styling->classes(new ExampleCard('A'), 'heder'))
        ->toThrow(InvalidSlotException::class, ExampleCard::class.' has no "heder" slot. Supported slots: root, header, title, body.')
        ->and(fn () => $styling->classes(new ExampleCard('A', new Tailwind(new Slot('footer', 'p-4'))), Tailwind::Wrapper))
        ->toThrow(InvalidSlotException::class, 'has no "footer" slot');
});

it('tolerates unknown slots when not strict', function (): void {
    $this->rebuildApplication(['gcego-chroma.strict' => false]);

    expect(app(ChromaManager::class)->classes(new ExampleCard('A', new Tailwind(new Slot('footer', 'p-4'))), 'footer'))->toBe('p-4');
});

it('styles components without declared slots only through their Tailwind node', function (): void {
    $component = new Component(new Tailwind('text-lg'));
    app(SlotRegistry::class)->register(Component::class, [], inherit: false, override: true);
    $custom = new class(new Tailwind('text-lg')) extends Component {};

    expect(app(ChromaManager::class)->classes($custom, Tailwind::Wrapper))->toBe('text-lg')
        ->and(app(ChromaManager::class)->classes($custom, 'anything'))->toBe('')
        ->and($component->childCount())->toBe(0);
});

it('rejects unknown themes', function (): void {
    app(ChromaManager::class)->classes(new ExampleCard('A'), Tailwind::Wrapper, 'missing');
})->throws(InvalidThemeException::class);

it('reads themes and overrides from configuration', function (): void {
    $this->rebuildApplication([
        'gcego-chroma.themes' => ['ocean' => ['tokens' => ['radius' => '1rem'], 'presets' => ['surface.card' => ['rounded-3xl']]]],
        'gcego-chroma.overrides' => [ExampleCard::class => [
            'root' => ['ring-1'],
            'title' => ['mode' => 'replace', 'classes' => ['text-xl', 'font-bold']],
        ]],
    ]);
    $styling = app(ChromaManager::class);
    $card = new ExampleCard('A');

    expect($styling->classes($card, Tailwind::Wrapper, 'ocean'))->toBe('rounded-3xl border border-gcego-border bg-gcego-surface text-gcego-text shadow-gcego-card ring-1')
        ->and($styling->classes($card, Tailwind::Title))->toBe('text-xl font-bold')
        ->and($styling->theme('ocean')->css())->toContain('--gcego-radius: 1rem;');
});

it('rejects invalid configured overrides', function (): void {
    $this->rebuildApplication(['gcego-chroma.overrides' => [ExampleCard::class => ['root' => ['mode' => 'invert', 'classes' => 'p-4']]]]);

    app(ChromaManager::class);
})->throws(InvalidThemeException::class, 'must be "merge", "replace" or "remove"');

it('caches static layers and invalidates them on registration', function (): void {
    $resolver = app(SlotResolver::class);
    $card = new ExampleCard('A');
    $before = $resolver->resolve($card, Tailwind::Wrapper);

    expect($resolver->resolve($card, Tailwind::Wrapper))->toBe($before);

    app(ChromaManager::class)->override(ExampleCard::class, new Slot(Tailwind::Wrapper, 'p-12'));

    expect($resolver->resolve($card, Tailwind::Wrapper)->contains('p-12'))->toBeTrue();
});

it('resolves presets with theme overrides and merges ad-hoc lists', function (): void {
    $styling = app(ChromaManager::class);
    $styling->registerTheme(new Theme('brand', presets: [Slot::remove('control.button', ['rounded-gcego'])]));

    expect($styling->preset('control.button', 'brand')->contains('rounded-gcego'))->toBeFalse()
        ->and($styling->preset('control.button')->contains('rounded-gcego'))->toBeTrue()
        ->and($styling->merge('rounded-lg p-4 bg-white', ['rounded-2xl', 'p-6']))->toBe('rounded-2xl p-6 bg-white');
});
