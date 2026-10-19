<?php

declare(strict_types=1);

use Gcego\Chroma\Classes\ClassList;
use Gcego\Chroma\Exceptions\InvalidSlotException;
use Gcego\Chroma\Exceptions\InvalidThemeException;
use Gcego\Chroma\Exceptions\InvalidTokenException;
use Gcego\Chroma\Exceptions\StyleConflictException;
use Gcego\Chroma\Presets\DefaultPresets;
use Gcego\Chroma\Presets\PresetRegistry;
use Gcego\Chroma\Slot;
use Gcego\Chroma\Themes\Theme;
use Gcego\Chroma\Themes\ThemeRegistry;
use Gcego\Swatches\Themes\ThemeRegistry as ColorThemes;

it('always has the default theme', function (): void {
    $themes = new ThemeRegistry;

    expect($themes->names())->toBe(['gcego'])
        ->and($themes->get('gcego')->css())->toBe('')
        ->and(fn () => $themes->get('missing'))->toThrow(InvalidThemeException::class, 'Theme "missing" is not registered. Registered themes: gcego.');
});

it('registers custom themes and rejects duplicates', function (): void {
    $themes = new ThemeRegistry;
    $themes->register(new Theme('ocean', ['radius' => '1rem']));

    expect($themes->has('ocean'))->toBeTrue()
        ->and(fn () => $themes->register(new Theme('ocean')))->toThrow(StyleConflictException::class, 'Theme "ocean" is already registered');

    $themes->register(new Theme('ocean', ['radius' => '2rem']), override: true);

    expect($themes->get('ocean')->tokens->get('radius'))->toBe('2rem');
});

it('emits scoped custom properties that do not leak light overrides into dark mode', function (): void {
    $theme = new Theme('ocean', tokens: ['radius-lg' => '1.25rem', 'radius' => '1rem'], dark: ['shadow-card' => 'none']);

    expect($theme->css())->toBe(':root[data-gcego-theme="ocean"]:not(.dark){--gcego-radius: 1rem; --gcego-radius-lg: 1.25rem;}:root.dark[data-gcego-theme="ocean"]{--gcego-shadow-card: none;}');
});

it('knows every gcego/swatches color theme', function (): void {
    $themes = new ThemeRegistry(ColorThemes::withDefaults());

    expect($themes->names())->toBe(['gcego', 'midnight', 'slate', 'ocean', 'forest'])
        ->and($themes->has('midnight'))->toBeTrue()
        ->and($themes->get('midnight')->css())->toBe('')
        ->and($themes->get('midnight')->label)->toBe('GCEGO Midnight')
        ->and($themes->has('missing'))->toBeFalse();
});

it('validates themes', function (): void {
    expect(fn () => new Theme('Ocean'))->toThrow(InvalidThemeException::class, 'not a valid theme name')
        ->and(fn () => new Theme('ocean', ['radius' => 'url(x)']))->toThrow(InvalidTokenException::class)
        ->and(fn () => new Theme('ocean', ['primary' => '#0e7490']))->toThrow(InvalidTokenException::class, 'gcego/swatches')
        ->and(fn () => new Theme('ocean', ['brand' => '#000']))->toThrow(InvalidTokenException::class, '"brand" is not a GCEGO design token')
        ->and(fn () => new Theme('ocean', presets: ['surface.card']))->toThrow(InvalidSlotException::class); // @phpstan-ignore argument.type
});

it('builds themes from configuration', function (): void {
    $theme = Theme::fromConfig('ocean', ['label' => 'Ocean', 'tokens' => ['radius' => '1rem'], 'presets' => ['surface.card' => ['rounded-3xl']]]);

    expect($theme->label)->toBe('Ocean')
        ->and($theme->presetOverrides('surface.card')[0]->classes->all())->toBe(['rounded-3xl'])
        ->and($theme->presetOverrides('surface.panel'))->toBe([])
        ->and(fn () => Theme::fromConfig('x', ['tokens' => 'nope']))->toThrow(InvalidThemeException::class);
});

it('ships the default presets and resolves them', function (): void {
    $presets = PresetRegistry::withDefaults();

    expect($presets->ids())->toBe(array_keys(DefaultPresets::Presets))
        ->and($presets->get('surface.card')->all())->toBe(DefaultPresets::Presets['surface.card'])
        ->and($presets->has('control.button'))->toBeTrue()
        ->and(fn () => $presets->get('missing'))->toThrow(InvalidSlotException::class, 'Style preset "missing" is not registered.');
});

it('registers presets, rejects duplicates and tracks versions', function (): void {
    $presets = new PresetRegistry;
    $version = $presets->version();
    $presets->register('reports.chart', ['rounded-gcego', 'p-4']);

    expect($presets->version())->toBe($version + 1)
        ->and(fn () => $presets->register('reports.chart', 'p-2'))->toThrow(StyleConflictException::class)
        ->and(fn () => $presets->register('Bad Id', 'p-2'))->toThrow(InvalidSlotException::class);

    $presets->register('reports.chart', 'p-2', override: true);

    expect($presets->get('reports.chart')->all())->toBe(['p-2']);
});

it('keeps preset classes literal, valid and free of dark: variants', function (): void {
    foreach (DefaultPresets::Presets as $id => $classes) {
        foreach ($classes as $class) {
            expect(str_starts_with($class, 'dark:') || str_contains($class, '{'))->toBeFalse($id.': '.$class);
        }

        expect(ClassList::from($classes)->count())->toBe(count($classes), $id.' has duplicates');
    }
});

it('gives interactive presets visible focus, reduced-motion safe transitions and touch-friendly size', function (string $preset): void {
    $classes = DefaultPresets::Presets[$preset];

    expect($classes)->toContain('focus-visible:outline-2')
        ->and($classes)->toContain('focus-visible:outline-gcego-ring')
        ->and($classes)->toContain('min-h-10');

    foreach ($classes as $class) {
        expect(str_starts_with($class, 'transition') || str_starts_with($class, 'duration'))->toBeFalse('transitions must be motion-safe: '.$class);
    }
})->with(['control.button', 'control.input', 'navigation.item']);

it('uses theme overrides as slots named after presets', function (): void {
    $slot = new Slot('surface.card', ['rounded-3xl']);

    expect($slot->name)->toBe('surface.card');
});
