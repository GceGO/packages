<?php

declare(strict_types=1);

use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Colors\ColorFormat;
use Gcego\Swatches\Exceptions\InvalidColorException;

it('parses every supported syntax', function (string $input, string $hex, string $css): void {
    $color = Color::parse($input);

    expect($color->toHex())->toBe($hex)
        ->and($color->toCss())->toBe($css);
})->with([
    'hex' => ['#2563EB', '#2563eb', '#2563eb'],
    'short hex' => ['#fff', '#ffffff', '#ffffff'],
    'hex with alpha' => ['#2563eb80', '#2563eb80', '#2563eb80'],
    'short hex with alpha' => ['#0008', '#00000088', '#00000088'],
    'rgb space' => ['rgb(37 99 235)', '#2563eb', 'rgb(37 99 235)'],
    'rgb percent alpha' => ['rgb(37 99 235 / 50%)', '#2563eb80', 'rgb(37 99 235 / 0.5)'],
    'rgba comma' => ['rgba(37, 99, 235, 0.25)', '#2563eb40', 'rgb(37 99 235 / 0.25)'],
    'rgb percentages' => ['rgb(100% 0% 0%)', '#ff0000', 'rgb(255 0 0)'],
    'hsl space' => ['hsl(221.2 83.2% 53.3%)', '#2563eb', 'hsl(221.2 83.2% 53.3%)'],
    'hsla comma' => ['hsla(120, 100%, 25%, 0.5)', '#00800080', 'hsl(120 100% 25% / 0.5)'],
    'hsl turn' => ['hsl(0.5turn 100% 50%)', '#00ffff', 'hsl(180 100% 50%)'],
    'oklch percent' => ['oklch(62.3% 0.214 259.815)', '#2b7fff', 'oklch(62.3% 0.214 259.815)'],
    'oklch number' => ['oklch(0.546 0.245 262.881)', '#155dfc', 'oklch(54.6% 0.245 262.881)'],
    'oklch none hue' => ['oklch(98.5% 0 none)', '#fafafa', 'oklch(98.5% 0 0)'],
    'oklab' => ['oklab(0.6 0.1 -0.1)', '#9f63ba', 'oklab(60% 0.1 -0.1)'],
    'named' => ['RebeccaPurple', '#663399', 'rebeccapurple'],
    'transparent' => ['transparent', '#00000000', 'transparent'],
]);

it('matches Tailwind CSS v4\'s published sRGB values for its OKLCH palette', function (string $oklch, string $hex): void {
    expect(Color::parse($oklch)->toHex())->toBe($hex);
})->with([
    'red-500' => ['oklch(63.7% 0.237 25.331)', '#fb2c36'],
    'blue-500' => ['oklch(62.3% 0.214 259.815)', '#2b7fff'],
    'emerald-500' => ['oklch(69.6% 0.17 162.48)', '#00bc7d'],
    'violet-500' => ['oklch(60.6% 0.25 292.717)', '#8e51ff'],
    'slate-500' => ['oklch(55.4% 0.046 257.417)', '#62748e'],
    'neutral-500' => ['oklch(55.6% 0 0)', '#737373'],
]);

it('converts between spaces without drift', function (): void {
    $color = Color::parse('#2563eb');

    foreach ([ColorFormat::Rgb, ColorFormat::Hsl, ColorFormat::Oklch, ColorFormat::Oklab, ColorFormat::Hex] as $format) {
        expect(Color::parse($color->as($format)->toCss())->toHex())->toBe('#2563eb', $format->value);
    }

    [$lightness, $chroma, $hue] = $color->toOklch();

    expect(round($lightness, 3))->toBe(0.546)
        ->and(round($chroma, 3))->toBe(0.215)
        ->and(round($hue))->toBe(263.0);
});

it('gamut maps colors outside sRGB instead of clipping them', function (): void {
    $wide = Color::parse('oklch(0.9 0.4 150)');
    [$r, $g, $b] = $wide->toSrgb();

    expect($wide->inSrgbGamut())->toBeFalse()
        ->and(min($r, $g, $b))->toBeGreaterThanOrEqual(0.0)
        ->and(max($r, $g, $b))->toBeLessThanOrEqual(1.0)
        ->and(abs(Color::parse($wide->toHex())->toOklch()[0] - 0.9))->toBeLessThan(0.03)
        ->and(Color::parse('oklch(1.2 0.1 40)')->toHex())->toBe('#ffffff')
        ->and(Color::parse('#2563eb')->inSrgbGamut())->toBeTrue();
});

it('composites translucent colors and compares displayed colors', function (): void {
    $half = Color::parse('rgb(0 0 0 / 50%)');

    expect($half->over(Color::white())->toHex())->toBe('#808080')
        ->and($half->isOpaque())->toBeFalse()
        ->and($half->withAlpha(1)->toHex())->toBe('#000000')
        ->and(Color::parse('#ff0000')->equals(Color::parse('red')))->toBeTrue()
        ->and(Color::parse('#ff0000')->equals(Color::parse('#fe0000')))->toBeFalse()
        ->and(Color::parse('#ffffff')->deltaE(Color::parse('#000000')))->toBeGreaterThan(0.99)
        ->and(Color::parse('#2563eb')->deltaE(Color::parse('#2563eb')))->toBe(0.0);
});

it('computes WCAG relative luminance', function (): void {
    expect(Color::white()->luminance())->toBe(1.0)
        ->and(Color::black()->luminance())->toBe(0.0)
        ->and(round(Color::parse('#767676')->luminance(), 4))->toBe(0.1812);
});

it('rejects values it cannot evaluate', function (string $input): void {
    expect(fn () => Color::parse($input))->toThrow(InvalidColorException::class);
})->with([
    'empty' => [''],
    'bad hex' => ['#12345'],
    'unknown name' => ['bluish'],
    'var()' => ['var(--gcego-primary)'],
    'reference' => ['{primary}'],
    'currentcolor' => ['currentcolor'],
    'calc' => ['rgb(calc(1) 0 0)'],
    'relative color' => ['oklch(from red l c h)'],
    'missing channel' => ['rgb(1 2)'],
    'mixed syntax' => ['rgb(1, 2 3)'],
    'comma oklch' => ['oklch(0.5, 0.1, 20)'],
    'hsl without percent in comma syntax' => ['hsl(10, 50, 50)'],
    'two alphas' => ['rgb(1 2 3 / 0.5 / 0.4)'],
    'infinite' => ['oklch(0.5 1e999 20)'],
    'too long' => [str_repeat('a', 101)],
]);

it('clamps channels to CSS ranges like browsers do', function (): void {
    expect(Color::parse('rgb(300 -20 128)')->toHex())->toBe('#ff0080')
        ->and(Color::parse('rgb(0 0 0 / 150%)')->isOpaque())->toBeTrue()
        ->and(Color::parse('hsl(-120 100% 50%)')->toHex())->toBe('#0000ff');
});

it('offers a non-throwing parser', function (): void {
    expect(Color::tryParse('nope'))->toBeNull()
        ->and(Color::tryParse('#000')?->toHex())->toBe('#000000');
});
