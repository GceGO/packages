<?php

declare(strict_types=1);

use Gcego\Swatches\Accessibility\Apca;
use Gcego\Swatches\Accessibility\ColorVision;
use Gcego\Swatches\Accessibility\ColorVisionSimulator;
use Gcego\Swatches\Colors\Color;
use Gcego\Swatches\Themes\Theme;
use Gcego\Swatches\Themes\ThemeRegistry;
use Gcego\Swatches\Tokens\ContrastRequirement;
use Gcego\Swatches\Validation\Issue;
use Gcego\Swatches\Validation\ThemeValidator;

it('matches the APCA-W3 reference values', function (string $text, string $background, float $lc): void {
    expect(round(Apca::contrast($text, $background), 2))->toBe($lc);
})->with([
    'gray on white' => ['#888888', '#ffffff', 63.06],
    'white on gray' => ['#ffffff', '#888888', -68.54],
    'black on white' => ['#000000', '#ffffff', 106.04],
    'white on black' => ['#ffffff', '#000000', -107.88],
    'identical' => ['#777777', '#777777', 0.0],
]);

it('is polarity-aware, composites alpha and maps requirements to APCA levels', function (): void {
    expect(Apca::contrast('#000', '#fff'))->toBeGreaterThan(0)
        ->and(Apca::contrast('#fff', '#000'))->toBeLessThan(0)
        ->and(Apca::contrast('rgb(0 0 0 / 0.5)', '#fff'))->toEqualWithDelta(Apca::contrast('#808080', '#fff'), 1.0)
        ->and(Apca::minimum(ContrastRequirement::Text))->toBe(60.0)
        ->and(Apca::minimum(ContrastRequirement::LargeText))->toBe(45.0)
        ->and(Apca::minimum(ContrastRequirement::NonText))->toBe(30.0)
        ->and(Apca::passes('#888888', '#ffffff'))->toBeTrue()
        ->and(Apca::passes('#999999', '#ffffff'))->toBeFalse()
        ->and(Apca::format(-68.5415))->toBe('Lc −68.5');
});

it('keeps neutrals neutral and collapses classic confusions', function (): void {
    foreach (ColorVision::cases() as $vision) {
        expect(ColorVisionSimulator::simulate(Color::parse('#808080'), $vision)->deltaE(Color::parse('#808080')))->toBeLessThan(0.01, $vision->value)
            ->and(ColorVisionSimulator::simulate(Color::white(), $vision)->toHex())->toBe('#ffffff');
    }

    $red = Color::parse('#d62728');
    $green = Color::parse('#2ca02c');

    expect(ColorVisionSimulator::simulate($red, ColorVision::Deuteranopia)->deltaE(ColorVisionSimulator::simulate($green, ColorVision::Deuteranopia)))
        ->toBeLessThan($red->deltaE($green) / 2)
        ->and(ColorVisionSimulator::simulate($red, ColorVision::Achromatopsia)->toOklch()[1])->toBeLessThan(0.001)
        ->and(ColorVisionSimulator::simulate($red, ColorVision::Deuteranopia, 0.0)->toHex())->toBe('#d62728');
});

it('finds the pair that collapses most', function (): void {
    $closest = ColorVisionSimulator::closestPair(['red' => Color::parse('#ff0000'), 'green' => Color::parse('#00aa00'), 'blue' => Color::parse('#0000ff')], ColorVision::Protanopia);

    expect($closest['pair'])->toBe(['red', 'green']);
});

it('reports APCA and color-vision findings only as advisory warnings, when asked', function (): void {
    $registry = ThemeRegistry::withDefaults();
    $registry->register(new Theme('traffic', ['success' => '#127a3e', 'danger' => '#c02a2f', 'warning' => '#a65308']));

    $plain = (new ThemeValidator($registry))->validate('gcego');
    $advised = (new ThemeValidator($registry, apca: true, colorVision: true))->validate('gcego');
    $codes = array_map(static fn ($issue): string => $issue->code, $advised->warnings());

    expect($plain->issues)->toBe([])
        ->and($advised->passes())->toBeTrue()
        ->and($codes)->toContain(Issue::Apca, Issue::ColorVision)
        ->and($advised->errors())->toBe([])
        ->and(collect($advised->contrast)->every(static fn ($result): bool => $result->apca !== 0.0))->toBeTrue();
});
