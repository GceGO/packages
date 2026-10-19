<?php

declare(strict_types=1);

use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Generators\CssGenerator;
use Gcego\Swatches\Palettes\ScaleGenerator;
use Gcego\Swatches\SwatchesManager;
use Gcego\Swatches\Themes\Accents;
use Gcego\Swatches\Themes\Theme;
use Gcego\Swatches\Themes\ThemeRegistry;
use Gcego\Swatches\Tokens\SemanticTokens;
use Gcego\Swatches\Tokens\TokenSet;

it('keeps the committed stylesheets identical to the generator output', function (string $file): void {
    $expected = app(SwatchesManager::class)->packageStylesheets()[$file];

    expect((string) file_get_contents(dirname(__DIR__, 2).'/resources/css/'.$file))->toBe($expected, "Run: php artisan gcego:swatches:generate --package ({$file} is stale)");
})->with(['tokens.css', 'tailwind.css', 'themes.css']);

it('declares light values on :root and only dark differences on :root.dark', function (): void {
    $css = (new CssGenerator)->tokens();
    [$light, $dark] = explode(':root.dark', $css);

    expect(preg_match_all('/^    --gcego-/m', $light))->toBe(count(SemanticTokens::Definitions))
        ->and($light)->toContain('color-scheme: light;')
        ->and($light)->toContain('--gcego-ring: var(--gcego-primary);')
        ->and($dark)->toContain('color-scheme: dark;')
        ->and($dark)->toContain('--gcego-primary: #3ec2b5;')
        ->and($dark)->not->toContain('--gcego-ring:');
});

it('never gives a gcego-* color spelling to names gcego/chroma uses for shadows', function (): void {
    expect((new CssGenerator)->tailwind())->not->toContain('--color-gcego-card:')
        ->not->toContain('--color-gcego-overlay:')
        ->and(count(SemanticTokens::GcegoPrefixed))->toBe(17);
});

it('maps every token to canonical and gcego-prefixed Tailwind colors', function (): void {
    $css = (new CssGenerator)->tailwind();

    foreach (SemanticTokens::all() as $name => $token) {
        $token->hasPrefixedUtility()
            ? expect($css)->toContain("--color-gcego-{$name}: var(--gcego-{$name});")
            : expect($css)->not->toContain("--color-gcego-{$name}: ");
        $token->hasCanonicalUtility()
            ? expect($css)->toContain("--color-{$name}: var(--gcego-{$name});")
            : expect($css)->not->toContain("--color-{$name}: ");
    }

    expect($css)->toContain('@theme inline {')
        ->and($css)->toContain('@custom-variant dark (&:where(.dark, .dark *));')
        ->and($css)->not->toContain('@import "tailwindcss"');
});

it('emits only what a theme changes, per mode', function (): void {
    $registry = ThemeRegistry::withDefaults();
    $registry->register(new Theme('campus', ['primary' => '#1d4ed8'], ['primary' => '#60a5fa']));

    $css = (new CssGenerator)->theme($registry->resolve('campus'), $registry->resolve('gcego'), minify: true);

    expect($css)->toBe(':root[data-gcego-theme="campus"]:not(.dark){--gcego-primary:#1d4ed8;}:root.dark[data-gcego-theme="campus"]{--gcego-primary:#60a5fa;}')
        ->and((new CssGenerator)->theme($registry->resolve('gcego'), $registry->resolve('gcego')))->toBe('');
});

it('re-declares dependent tokens inside accent swatch scopes', function (): void {
    $registry = ThemeRegistry::withDefaults();
    $css = (new CssGenerator)->accents(Accents::all(['red']), $registry->resolve('gcego'));

    expect($css)->toMatch('/:root\[data-gcego-accent="red"\]:not\(\.dark\)\{[^}]*--gcego-primary:#b91c1c;/')
        ->and($css)->toContain(':root.dark[data-gcego-accent="red"]{')
        ->and($css)->toMatch('/\[data-gcego-accent-swatch="red"\]\{[^}]*--gcego-link:var\(--gcego-primary\);/')
        ->and($css)->toContain(':root.dark [data-gcego-accent-swatch="red"]{')
        ->and($css)->toContain('[data-gcego-accent-swatch="default"]{')
        ->and($css)->not->toContain('--gcego-surface:');
});

it('emits brand overrides that outrank themes by source order only', function (): void {
    $css = (new CssGenerator)->brand(new TokenSet(['primary' => '#2563eb']), new TokenSet(['primary' => '#8ab2ff']));

    expect($css)->toBe(':root[data-gcego-theme]:not(.dark){--gcego-primary:#2563eb;}:root.dark[data-gcego-theme]{--gcego-primary:#8ab2ff;}')
        ->and((new CssGenerator)->brand(new TokenSet, new TokenSet))->toBe('');
});

it('emits application palettes as Tailwind theme variables', function (): void {
    $palette = (new ScaleGenerator)->generate('brand', Color::parse('#2563eb'));
    $css = (new CssGenerator)->palettes([$palette]);

    expect($css)->toStartWith("@theme {\n")
        ->and($css)->toContain('--color-brand-50: oklch(')
        ->and($css)->toContain('--color-brand-700: #2563eb;')
        ->and(substr_count($css, '--color-brand-'))->toBe(11);
});
