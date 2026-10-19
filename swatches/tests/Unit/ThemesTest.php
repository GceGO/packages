<?php

declare(strict_types=1);

use Gcego\Swatches\Accessibility\Contrast;
use Gcego\Swatches\Accessibility\ContrastAuditor;
use Gcego\Swatches\Exceptions\DuplicateRegistrationException;
use Gcego\Swatches\Exceptions\InvalidThemeException;
use Gcego\Swatches\Exceptions\InvalidTokenException;
use Gcego\Swatches\Themes\Accent;
use Gcego\Swatches\Themes\Accents;
use Gcego\Swatches\Themes\BuiltInThemes;
use Gcego\Swatches\Themes\Theme;
use Gcego\Swatches\Themes\ThemeRegistry;
use Gcego\Swatches\Tokens\SemanticTokens;
use Gcego\Swatches\Validation\Issue;
use Gcego\Swatches\Validation\ThemeValidator;

it('ships coherent presets that pass WCAG 2.2 AA in light and dark mode', function (string $name): void {
    $registry = ThemeRegistry::withDefaults();
    $results = (new ContrastAuditor)->audit($registry->resolve($name));
    $failures = array_map(static fn ($result): string => "{$result->mode()}: {$result->foreground} on {$result->background} ".Contrast::format($result->ratio), array_filter($results, static fn ($result): bool => ! $result->passes()));

    expect($failures)->toBe([])
        ->and(count($results))->toBe(2 * count(SemanticTokens::ContrastPairs))
        ->and((new ThemeValidator($registry))->validate($name)->issues)->toBe([]);
})->with(['gcego', 'midnight', 'slate', 'ocean', 'forest']);

it('keeps chart series distinguishable', function (string $name): void {
    $theme = ThemeRegistry::withDefaults()->resolve($name);

    foreach ([false, true] as $dark) {
        foreach (SemanticTokens::ChartSeries as $i => $first) {
            foreach (array_slice(SemanticTokens::ChartSeries, $i + 1) as $second) {
                expect($theme->color($first, $dark)->deltaE($theme->color($second, $dark)))->toBeGreaterThanOrEqual(0.07, "{$first}/{$second}");
            }
        }
    }
})->with(['gcego', 'midnight', 'slate', 'ocean', 'forest']);

it('inherits base first and does not leak light overrides into dark mode', function (): void {
    $registry = ThemeRegistry::withDefaults();
    $registry->register(new Theme('campus', ['primary' => '#1d4ed8'], extends: 'ocean'));

    $campus = $registry->resolve('campus');

    expect($campus->chain)->toBe(['gcego', 'ocean', 'campus'])
        ->and($campus->color('primary')->toHex())->toBe('#1d4ed8')
        ->and($campus->color('primary', dark: true)->toHex())->toBe('#22d3ee')
        ->and($campus->color('background')->toHex())->toBe('#f4f7f9')
        ->and($campus->color('ring')->toHex())->toBe('#1d4ed8');
});

it('detects unknown parents and circular inheritance', function (): void {
    $registry = new ThemeRegistry;
    $registry->register(new Theme('a', extends: 'b'));
    $registry->register(new Theme('b', extends: 'a'));
    $registry->register(new Theme('orphan', extends: 'missing'));

    expect(fn () => $registry->resolve('a'))->toThrow(InvalidThemeException::class, 'cycle')
        ->and(fn () => $registry->resolve('orphan'))->toThrow(InvalidThemeException::class, 'extends "missing"')
        ->and(fn () => new Theme('self', extends: 'self'))->toThrow(InvalidThemeException::class)
        ->and((new ThemeValidator($registry))->validate('a')->errors()[0]->code)->toBe(Issue::Inheritance);
});

it('rejects duplicate names, invalid identifiers and a base theme with a parent', function (): void {
    $registry = ThemeRegistry::withDefaults();

    expect(fn () => $registry->register(new Theme('ocean')))->toThrow(DuplicateRegistrationException::class)
        ->and(fn () => new Theme('Ocean Blue'))->toThrow(InvalidThemeException::class)
        ->and(fn () => new Theme('ocean', label: '<b>Ocean</b>'))->toThrow(InvalidThemeException::class)
        ->and(fn () => $registry->register(new Theme('gcego', extends: 'ocean'), override: true))->toThrow(InvalidThemeException::class);
});

it('builds themes from configuration and refuses unknown keys or tokens', function (): void {
    $theme = Theme::fromConfig('campus', ['label' => 'Campus', 'tokens' => ['primary' => '#1d4ed8'], 'dark' => ['primary' => '#60a5fa']]);

    expect($theme->light->toArray())->toBe(['primary' => '#1d4ed8'])
        ->and($theme->extends)->toBe('gcego')
        ->and($theme->label())->toBe('Campus')
        ->and(fn () => Theme::fromConfig('campus', ['css' => 'body{}']))->toThrow(InvalidThemeException::class)
        ->and(fn () => Theme::fromConfig('campus', ['light' => ['font-sans' => 'Inter']]))->toThrow(InvalidTokenException::class)
        ->and(fn () => Theme::fromConfig('campus', ['light' => '#fff']))->toThrow(InvalidThemeException::class);
});

it('reports contrast failures with a suggested fix, and broken references', function (): void {
    $registry = ThemeRegistry::withDefaults();
    $registry->register(new Theme('pale', ['primary' => '#7dd3fc', 'link' => '{nowhere}']));
    $registry->register(new Theme('faint', ['muted-foreground' => '#a0a0a0']));

    $broken = (new ThemeValidator($registry))->validate('pale');
    $faint = (new ThemeValidator($registry))->validate('faint');
    $issue = $faint->errors()[0];

    expect($broken->errors()[0]->code)->toBe(Issue::BrokenReference)
        ->and($faint->passes())->toBeFalse()
        ->and($issue->code)->toBe(Issue::Contrast)
        ->and($issue->token)->toBe('muted-foreground')
        ->and($issue->suggestion)->toStartWith('Set muted-foreground to #');
});

it('warns about out-of-gamut colors and indistinct chart series', function (): void {
    $registry = ThemeRegistry::withDefaults();
    $registry->register(new Theme('vivid', ['chart-2' => '{chart-1}', 'chart-5' => 'oklch(0.55 0.35 0)']));

    $codes = array_map(static fn ($issue): string => $issue->code, (new ThemeValidator($registry))->validate('vivid')->warnings());

    expect($codes)->toContain(Issue::OutOfGamut, Issue::IndistinctSeries);
});

it('keeps every built-in preset label and name valid', function (): void {
    foreach (BuiltInThemes::all() as $theme) {
        expect($theme->extends)->toBe('gcego')
            ->and($theme->label())->toStartWith('GCEGO ');
    }
});

it('keeps text readable on every main color users may pick', function (): void {
    foreach (Accents::all() as $accent) {
        expect(Contrast::ratio($accent->light->resolve('primary-foreground'), $accent->light->resolve('primary')))->toBeGreaterThanOrEqual(4.5)
            ->and(Contrast::ratio($accent->dark->resolve('primary-foreground'), $accent->dark->resolve('primary')))->toBeGreaterThanOrEqual(4.5);
    }

    expect(array_keys(Accents::all(['red', 'nope', 'blue'])))->toBe(['red', 'blue'])
        ->and(fn () => new Accent('odd', 'Odd', ['surface' => '#000000'], []))->toThrow(InvalidThemeException::class)
        ->and(fn () => new Accent('odd', 'Odd', ['primary' => 'red;}body{display:none'], []))->toThrow(InvalidTokenException::class)
        ->and(fn () => new Accent('Bad Name', 'Bad', [], []))->toThrow(InvalidThemeException::class);
});
