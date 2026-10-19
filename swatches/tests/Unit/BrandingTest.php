<?php

declare(strict_types=1);

use Gcego\Swatches\Accessibility\ContrastAuditor;
use Gcego\Swatches\Branding\Brand;
use Gcego\Swatches\Branding\BrandCompiler;
use Gcego\Swatches\Exceptions\InvalidColorException;
use Gcego\Swatches\Exceptions\InvalidThemeException;
use Gcego\Swatches\Themes\ResolvedTheme;
use Gcego\Swatches\Themes\ThemeRegistry;

it('produces brand overrides that keep the theme accessible', function (string $primary, string $accent): void {
    $theme = ThemeRegistry::withDefaults()->resolve('gcego');
    $compiled = (new BrandCompiler)->compile(Brand::fromArray(['primary' => $primary, 'accent' => $accent]), $theme);
    $branded = new ResolvedTheme($theme->theme, $theme->chain, $theme->light->with($compiled->light), $theme->dark->with($compiled->dark));

    $failures = array_filter((new ContrastAuditor)->audit($branded), static fn ($result): bool => ! $result->passes());

    expect(array_map(static fn ($result): string => "{$result->mode()} {$result->foreground}/{$result->background}", $failures))->toBe([]);
})->with([
    ['#2563eb', '#f59e0b'],
    ['#facc15', '#22c55e'],
    ['#ff0000', '#ec4899'],
    ['#111111', '#ffffff'],
    ['#ffffff', '#000000'],
    ['oklch(0.7 0.3 140)', 'oklch(0.6 0.25 300)'],
    ['#0ea5e9', '#a3e635'],
]);

it('uses the brand color unchanged when it already passes, and explains adjustments', function (): void {
    $theme = ThemeRegistry::withDefaults()->resolve('gcego');
    $compiler = new BrandCompiler;

    $blue = $compiler->compile(Brand::fromArray(['primary' => '#2563eb']), $theme);
    $yellow = $compiler->compile(Brand::fromArray(['primary' => '#facc15']), $theme);

    expect($blue->light->get('primary')?->toString())->toBe('#2563eb')
        ->and($blue->light->get('primary-foreground')?->toString())->toBe('#ffffff')
        ->and($blue->notes)->toBe([])
        ->and($yellow->light->get('primary')?->toString())->not->toBe('#facc15')
        ->and($yellow->notes[0])->toContain('darkened')
        ->and($compiler->compile(Brand::fromArray(['primary' => '#facc15']), $theme))->toEqual($yellow);
});

it('compiles nothing for an empty brand', function (): void {
    $compiled = (new BrandCompiler)->compile(new Brand, ThemeRegistry::withDefaults()->resolve('gcego'));

    expect($compiled->isEmpty())->toBeTrue()
        ->and((new Brand)->isEmpty())->toBeTrue();
});

it('accepts only colors and a theme name from tenant input', function (): void {
    $brand = Brand::fromArray(['primary' => ' #2563EB ', 'accent' => '', 'theme' => 'ocean']);

    expect($brand->toArray())->toBe(['primary' => '#2563eb', 'accent' => null, 'theme' => 'ocean'])
        ->and($brand->fingerprint())->toBe(Brand::fromArray(['primary' => 'rgb(37 99 235)', 'theme' => 'ocean'])->fingerprint())
        ->and(fn () => Brand::fromArray(['primary' => 'red;}</style><script>alert(1)</script>']))->toThrow(InvalidColorException::class)
        ->and(fn () => Brand::fromArray(['css' => 'body{}']))->toThrow(InvalidThemeException::class)
        ->and(fn () => Brand::fromArray(['primary' => ['#fff']]))->toThrow(InvalidThemeException::class)
        ->and(fn () => Brand::fromArray(['theme' => '../ocean']))->toThrow(InvalidThemeException::class);
});
