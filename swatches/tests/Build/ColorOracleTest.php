<?php

declare(strict_types=1);

use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Colors\ColorFormat;
use Gcego\Swatches\Palettes\ScaleGenerator;

/**
 * The color math checked against an independent, widely used implementation:
 * Lightning CSS converts modern color syntax to sRGB for old browsers.
 * Lightning CSS uses its own fallback method outside sRGB, so only in-gamut colors
 * are compared here; gamut mapping is pinned to Tailwind's published values in ColorsTest.
 *
 * @param  list<string>  $colors
 * @return list<string|null>
 */
function oracle(array $colors): array
{
    /** @var list<string|null> */
    return nodeJson('tests/Build/lightning-oracle.mjs', [(string) json_encode($colors)]);
}

/**
 * "#639" → [102, 51, 153]
 *
 * @return array{int, int, int}
 */
function channels(string $hex): array
{
    $hex = ltrim($hex, '#');
    $hex = strlen($hex) === 3 ? $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2] : substr($hex, 0, 6);

    return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
}

/**
 * Deterministic in-gamut colors across the whole OKLCH space.
 *
 * @return list<Color>
 */
function sampleColors(int $count): array
{
    mt_srand(20261018);
    $colors = [];

    while (count($colors) < $count) {
        $lightness = mt_rand(5, 98) / 100;
        $hue = mt_rand(0, 3599) / 10;
        $chroma = ScaleGenerator::maxChroma($lightness, $hue) * mt_rand(0, 95) / 100;
        $colors[] = Color::oklch($lightness, $chroma, $hue);
    }

    return $colors;
}

it('converts OKLCH, OKLab and HSL to the same sRGB as Lightning CSS', function (ColorFormat $format): void {
    $colors = sampleColors(150);
    $inputs = array_map(static fn (Color $color): string => $color->as($format)->toCss(), $colors);
    $expected = oracle($inputs);

    foreach ($inputs as $index => $input) {
        expect($expected[$index])->not->toBeNull($input);

        $ours = channels(Color::parse($input)->toHex());
        $theirs = channels((string) $expected[$index]);

        foreach ([0, 1, 2] as $channel) {
            expect(abs($ours[$channel] - $theirs[$channel]))->toBeLessThanOrEqual(1, "{$input}: ours ".Color::parse($input)->toHex().", Lightning CSS {$expected[$index]}");
        }
    }
})->with([ColorFormat::Oklch, ColorFormat::Oklab, ColorFormat::Hsl]);

it('round-trips sRGB through our OKLCH and back through Lightning CSS', function (): void {
    mt_srand(42);
    $hexes = array_map(static fn (): string => sprintf('#%02x%02x%02x', mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)), range(1, 150));
    $expected = oracle(array_map(static fn (string $hex): string => Color::parse($hex)->toOklchString(), $hexes));

    foreach ($hexes as $index => $hex) {
        $theirs = channels((string) $expected[$index]);
        $original = channels($hex);

        foreach ([0, 1, 2] as $channel) {
            expect(abs($original[$channel] - $theirs[$channel]))->toBeLessThanOrEqual(1, "{$hex} → ".Color::parse($hex)->toOklchString()." → {$expected[$index]}");
        }
    }
});

it('agrees on named colors and alpha', function (): void {
    $inputs = ['rebeccapurple', 'cornflowerblue', 'oklch(0.6 0.1 200 / 0.5)', 'hsl(10 60% 40% / 25%)'];
    $expected = oracle($inputs);

    expect(channels((string) $expected[0]))->toBe(channels(Color::parse('rebeccapurple')->toHex()))
        ->and(channels((string) $expected[1]))->toBe(channels(Color::parse('cornflowerblue')->toHex()))
        ->and($expected[2])->toContain('.5')
        ->and($expected[3])->toContain('.25');
});
