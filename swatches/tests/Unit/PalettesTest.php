<?php

declare(strict_types=1);

use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Exceptions\DuplicateRegistrationException;
use Gcego\Swatches\Exceptions\InvalidPaletteException;
use Gcego\Swatches\Palettes\BuiltInPalettes;
use Gcego\Swatches\Palettes\Palette;
use Gcego\Swatches\Palettes\PaletteRegistry;
use Gcego\Swatches\Palettes\ScaleGenerator;

it('mirrors the installed Tailwind CSS palette exactly', function (): void {
    $theme = dirname(__DIR__, 4).'/node_modules/tailwindcss/theme.css';
    $package = dirname($theme).'/package.json';

    if (! is_file($theme)) {
        $this->markTestSkipped('node_modules/tailwindcss is not installed.');
    }

    $version = json_decode((string) file_get_contents($package), true)['version'] ?? null;
    $css = (string) file_get_contents($theme);

    expect($version)->toBe(BuiltInPalettes::TailwindVersion);

    foreach (BuiltInPalettes::Scales as $family => $steps) {
        foreach ($steps as $step => $value) {
            expect($css)->toContain("--color-{$family}-{$step}: {$value};");
        }
    }
});

it('registers the reference families with every step', function (): void {
    $registry = PaletteRegistry::withDefaults();

    expect($registry->names())->toBe(['neutral', 'slate', 'blue', 'indigo', 'violet', 'emerald', 'amber', 'red', 'teal', 'cyan']);

    foreach ($registry->all() as $palette) {
        expect($palette->steps())->toBe(Palette::Steps)
            ->and($palette->isBuiltIn())->toBeTrue();
    }

    expect($registry->get('blue')->get(600)->toHex())->toBe('#155dfc')
        ->and($registry->custom())->toBe([]);
});

it('rejects duplicate, unnamed and malformed palettes', function (): void {
    $registry = PaletteRegistry::withDefaults();

    expect(fn () => $registry->register(Palette::fromStrings('blue', [500 => '#000'])))->toThrow(DuplicateRegistrationException::class)
        ->and(fn () => Palette::fromStrings('Bad Name', [500 => '#000']))->toThrow(InvalidPaletteException::class)
        ->and(fn () => Palette::fromStrings('brand', [550 => '#000']))->toThrow(InvalidPaletteException::class)
        ->and(fn () => Palette::fromStrings('brand', []))->toThrow(InvalidPaletteException::class)
        ->and(fn () => $registry->get('nope'))->toThrow(InvalidPaletteException::class)
        ->and(fn () => $registry->get('blue')->get(25))->toThrow(InvalidPaletteException::class);

    $registry->register(Palette::fromStrings('blue', [500 => '#000']), override: true);

    expect($registry->get('blue')->steps())->toBe([500]);
});

it('generates a scale anchored on the brand color', function (): void {
    $palette = (new ScaleGenerator)->generate('brand', Color::parse('#2563EB'));
    $anchor = (int) $palette->metadata['anchor'];

    expect($palette->steps())->toBe(Palette::Steps)
        ->and($palette->get($anchor)->toHex())->toBe('#2563eb')
        ->and($palette->metadata['source'])->toBe('generated')
        ->and($palette->isBuiltIn())->toBeFalse();

    $previous = 1.0;

    foreach ($palette->colors as $step => $color) {
        [$lightness, , $hue] = $color->toOklch();

        expect($lightness)->toBeLessThan($previous, "step {$step} must be darker than the one before")
            ->and($color->inSrgbGamut())->toBeTrue("step {$step} is displayable");

        if ($step !== $anchor) {
            expect(abs($hue - Color::parse('#2563eb')->toOklch()[2]))->toBeLessThan(0.01);
        }

        $previous = $lightness;
    }
});

it('generates deterministically, including for gray and extreme colors', function (string $brand): void {
    $generator = new ScaleGenerator;

    expect($generator->generate('brand', Color::parse($brand))->toArray())->toBe($generator->generate('brand', Color::parse($brand))->toArray());

    foreach ($generator->generate('brand', Color::parse($brand))->colors as $color) {
        expect($color->inSrgbGamut())->toBeTrue();
    }
})->with(['#2563eb', '#777777', '#ffff00', '#000000', '#ffffff', 'oklch(0.7 0.37 145)']);
