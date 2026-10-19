<?php

declare(strict_types=1);

use Gcego\Swatches\Accessibility\Contrast;
use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Tokens\ContrastRequirement;

it('computes WCAG 2.x contrast ratios', function (): void {
    expect(Contrast::ratio('#000000', '#ffffff'))->toBe(21.0)
        ->and(Contrast::ratio('#fff', 'white'))->toBe(1.0)
        ->and(round(Contrast::ratio('#767676', '#ffffff'), 2))->toBe(4.54)
        ->and(Contrast::ratio('#ffffff', '#767676'))->toBe(Contrast::ratio('#767676', '#ffffff'));
});

it('never rounds a failing ratio up to a pass', function (): void {
    // #777777 on white is 4.478…: it must fail 4.5:1 and display as 4.47:1.
    expect(Contrast::passes('#777777', '#ffffff'))->toBeFalse()
        ->and(Contrast::format(Contrast::ratio('#777777', '#ffffff')))->toBe('4.47:1')
        ->and(Contrast::passes('#777777', '#ffffff', ContrastRequirement::LargeText))->toBeTrue();
});

it('composites translucent colors before measuring', function (): void {
    // 50% black over white displays as mid gray (#808080 after 8-bit rounding).
    expect(Contrast::ratio('rgb(0 0 0 / 0.5)', '#ffffff'))->toEqualWithDelta(Contrast::ratio('#808080', '#ffffff'), 0.05)
        // A translucent background is painted over the backdrop first.
        ->and(Contrast::ratio('#ffffff', 'rgb(0 0 0 / 0.5)', '#000000'))->toBe(21.0)
        ->and(Contrast::ratio('#ffffff', 'rgb(0 0 0 / 0.5)'))->toEqualWithDelta(Contrast::ratio('#ffffff', '#808080'), 0.05);
});

it('suggests the nearest passing foreground with the same hue', function (): void {
    $suggestion = Contrast::suggest('#5eead4', '#ffffff', 4.5);

    expect($suggestion)->not->toBeNull()
        ->and(Contrast::ratio((string) $suggestion?->toHex(), '#ffffff'))->toBeGreaterThanOrEqual(4.5)
        ->and(abs(($suggestion?->toOklch()[2] ?? 0) - 180))->toBeLessThan(15)
        ->and(Contrast::suggest('#000000', '#ffffff', 4.5)?->toHex())->toBe('#000000')
        ->and(Contrast::suggest('#777777', '#808080', 21.0))->toBeNull();
});

it('never suggests a hex value that rounds below the requirement', function (string $foreground, string $background, float $minimum): void {
    $suggestion = Contrast::suggest($foreground, $background, $minimum);

    expect($suggestion)->not->toBeNull()
        ->and(Contrast::ratio(Color::parse((string) $suggestion?->toHex()), $background))->toBeGreaterThanOrEqual($minimum);
})->with([
    ['#888888', '#ffffff', 4.5],
    ['#9aa3b2', '#ffffff', 4.5],
    ['#4a5468', '#11151c', 4.5],
    ['#3ec2b5', '#e7f3f2', 4.5],
    ['#b0b0b0', '#ffffff', 3.0],
    ['#2563eb', '#1d4ed8', 4.5],
]);
