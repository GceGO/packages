<?php

declare(strict_types=1);

use Gcego\Core\Packages\PackageManager;
use Gcego\Swatches\Branding\NullBrandResolver;
use Gcego\Swatches\Contracts\BrandResolver;
use Gcego\Swatches\Exceptions\InvalidThemeException;
use Gcego\Swatches\Facades\Swatches;
use Gcego\Swatches\Palettes\PaletteRegistry;
use Gcego\Swatches\SwatchesManager;
use Gcego\Swatches\Themes\Theme;
use Gcego\Swatches\Themes\ThemeSource;

it('registers the manager, facade and the GCEGO package descriptor', function (): void {
    expect(app(SwatchesManager::class))->toBe(app('gcego.swatches'))
        ->and(Swatches::palette('blue')->get(600)->toHex())->toBe('#155dfc')
        ->and(Swatches::theme()->name)->toBe('gcego')
        ->and(Swatches::token('primary')->description)->toContain('Primary')
        ->and(round(Swatches::contrast('#111827', '#FFFFFF'), 2))->toBe(17.74)
        ->and(app(BrandResolver::class))->toBeInstanceOf(NullBrandResolver::class);

    if (class_exists(PackageManager::class) && method_exists(PackageManager::class, 'has')) {
        expect(app(PackageManager::class)->has('gcego/swatches'))->toBeTrue();
    }
});

it('loads palettes and themes from configuration', function (): void {
    $this->rebuildApplication([
        'gcego-swatches.theme' => 'campus',
        'gcego-swatches.palettes' => ['brand' => '#2563eb', 'sand' => [50 => '#fbf8f1', 500 => '#c8a96a']],
        'gcego-swatches.themes' => ['campus' => ['label' => 'Campus', 'extends' => 'ocean', 'light' => ['primary' => '#1d4ed8'], 'dark' => ['primary' => '#60a5fa']]],
    ]);

    $palettes = app(PaletteRegistry::class);

    expect($palettes->get('brand')->metadata['source'])->toBe('generated')
        ->and($palettes->get('sand')->steps())->toBe([50, 500])
        ->and(array_keys($palettes->custom()))->toBe(['brand', 'sand'])
        ->and(Swatches::theme()->name)->toBe('campus')
        ->and(Swatches::theme()->source)->toBe(ThemeSource::Config)
        ->and(Swatches::color('primary')->toHex())->toBe('#1d4ed8')
        ->and(Swatches::color('primary', dark: true)->toHex())->toBe('#60a5fa')
        ->and(Swatches::resolve()->chain)->toBe(['gcego', 'ocean', 'campus']);
});

it('refuses to boot with structurally invalid themes', function (array $config, string $message): void {
    expect(function () use ($config): void {
        $this->rebuildApplication($config);
        app(SwatchesManager::class);
    })->toThrow(InvalidThemeException::class, $message);
})->with([
    'unknown parent' => [['gcego-swatches.themes' => ['campus' => ['extends' => 'nope']]], 'extends "nope"'],
    'broken reference' => [['gcego-swatches.themes' => ['campus' => ['light' => ['link' => '{nowhere}']]]], 'nowhere'],
    'unknown default theme' => [['gcego-swatches.theme' => 'nope'], 'not a registered theme'],
    'bad appearance' => [['gcego-swatches.appearance.default' => 'dim'], 'appearance.default'],
    'bad storage key' => [['gcego-swatches.appearance.storage_key' => 'a b'], 'storage_key'],
]);

it('enforces contrast only when configured to', function (): void {
    $faint = ['gcego-swatches.themes' => ['faint' => ['light' => ['muted-foreground' => '#b0b0b0']]]];

    $this->rebuildApplication($faint);

    expect(Swatches::validate('faint')->passes())->toBeFalse();

    expect(function () use ($faint): void {
        $this->rebuildApplication([...$faint, 'gcego-swatches.validation.enforce_contrast' => true]);
        app(SwatchesManager::class);
    })->toThrow(InvalidThemeException::class, 'muted-foreground on');
});

it('validates themes registered at runtime', function (): void {
    Swatches::registerTheme(new Theme('runtime', ['primary' => '#1d4ed8']));

    expect(Swatches::hasTheme('runtime'))->toBeTrue()
        ->and(fn () => Swatches::registerTheme(new Theme('broken', ['link' => '{gone}'])))->toThrow(InvalidThemeException::class);
});
