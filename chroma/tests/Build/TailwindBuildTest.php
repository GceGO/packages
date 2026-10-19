<?php

declare(strict_types=1);

use Gcego\Chroma\Presets\DefaultPresets;
use Gcego\Chroma\Tokens\DefaultTokens;

/**
 * Real Tailwind CSS v4 builds through the monorepo's @tailwindcss/node and @tailwindcss/oxide.
 */
function build(string $entry, bool $minify = true): array
{
    $result = nodeJson('tests/Build/compile.mjs', $minify ? [$entry] : [$entry, '--no-minify']);
    $css = $result['css'] ?? null;
    $files = $result['files'] ?? null;

    expect($css)->toBeString()->and($files)->toBeArray();

    /** @var array{css: string, candidates: int, files: list<string>} $result */
    return $result;
}

/**
 * Tailwind's escaped selector for a class: "md:grid-cols-2" -> ".md\:grid-cols-2".
 */
function selector(string $class): string
{
    return '.'.preg_replace('/([^a-zA-Z0-9_-])/', '\\\\$1', $class);
}

it('compiles every default preset utility in a production build', function (): void {
    $css = build('resources/css/build-entry.css')['css'];

    foreach (DefaultPresets::Presets as $id => $classes) {
        foreach ($classes as $class) {
            expect(str_contains($css, selector($class)))->toBeTrue("{$id}: {$class} is missing from the build");
        }
    }
});

it('scans only the documented GCEGO package locations', function (): void {
    $files = build('resources/css/build-entry.css')['files'];
    $packages = dirname(__DIR__, 3);

    expect($files)->toContain($packages.'/chroma/src/Presets/DefaultPresets.php')
        ->and($files)->toContain($packages.'/runtime/resources/views/renderers/blueprint.blade.php');

    foreach ($files as $file) {
        expect((bool) preg_match('#/(resources/views|src/Presets)/#', $file))->toBeTrue($file.' should not be scanned');
    }
});

it('emits light and dark token values and the dark variant', function (): void {
    $css = (string) preg_replace('/\s+/', '', build('resources/css/build-entry.css', minify: false)['css']);

    // Lightning CSS rewrites non-color values (.5rem, #0f17290d), so only their presence is checked.
    foreach (DefaultTokens::names() as $name) {
        expect(substr_count($css, "--gcego-{$name}:"))->toBeGreaterThanOrEqual(2, $name);
    }

    // Colors come from gcego/swatches through gcego.css.
    foreach (['surface' => ['#fff', '#11151c'], 'primary' => ['#0b7570', '#3ec2b5']] as $name => [$light, $dark]) {
        expect($css)->toContain("--gcego-{$name}:{$light}")
            ->and($css)->toContain("--gcego-{$name}:{$dark}");
    }

    expect($css)->toContain(':root.dark{')
        ->and($css)->toContain('color-scheme:dark;')
        ->and($css)->toContain('var(--gcego-surface)');
});

it('compiles responsive, state, reduced-motion and dark utilities', function (): void {
    $css = build('tests/Build/app-entry.css')['css'];

    expect($css)->toContain('@media (prefers-reduced-motion:no-preference)')
        ->and($css)->toContain(selector('md:grid-cols-2'))
        ->and($css)->toContain(selector('xl:grid-cols-3'))
        ->and($css)->toContain(selector('focus-visible:outline-gcego-ring').':focus-visible')
        ->and($css)->toContain(selector('aria-invalid:border-gcego-danger').'[aria-invalid=true]')
        ->and($css)->toContain(selector('data-loading:opacity-60').'[data-loading]')
        ->and($css)->toContain(selector('lg:grid-cols-4'))
        ->and($css)->toContain(selector('dark:bg-gcego-surface-muted').':where(.dark,.dark *)');
});

it('includes literal classes from application sources but not concatenated ones', function (): void {
    $css = build('tests/Build/app-entry.css')['css'];

    // grid-cols-11 is used nowhere as a literal (other packages list grid-cols-1 to 6).
    expect($css)->toContain(selector('grid-cols-7'))
        ->and($css)->not->toContain(selector('grid-cols-11'));
});

it('produces a compact minified stylesheet', function (): void {
    $minified = build('resources/css/build-entry.css')['css'];
    $readable = build('resources/css/build-entry.css', minify: false)['css'];

    // Every GCEGO package's utilities, measured 2026-10-19 at 100 KB raw / 15 KB gzip:
    // gcego/layouts' responsive grid, span and gap matrix (~17 KB), gcego/tables' premium table and
    // its custom controls (~25 KB), gcego/forms' custom controls (~9 KB), navigation's icon rail
    // (~6 KB), panels (~4.5 KB), infolists (~2.5 KB), gcego/swatches' color tokens (~3.6 KB;
    // presets are runtime CSS). Transfer size is the budget that matters to users.
    expect(strlen($minified))->toBeLessThan(strlen($readable))
        ->and(strlen($minified))->toBeLessThan(108 * 1024)
        ->and(strlen((string) gzencode($minified, 9)))->toBeLessThan(17 * 1024);
});

it('applies the stored, default or system appearance before paint', function (string $stored, string $systemDark, string $default, bool $dark, string $mode): void {
    $result = nodeJson('tests/Build/appearance-harness.mjs', [$stored, $systemDark, $default, 'true']);

    expect($result['initial'])->toBe(['dark' => $dark, 'mode' => $mode, 'scheme' => $dark ? 'dark' : 'light']);
})->with([
    'stored dark' => ['dark', '0', 'system', true, 'dark'],
    'stored light beats dark system' => ['light', '1', 'system', false, 'light'],
    'system follows the OS (dark)' => ['none', '1', 'system', true, 'system'],
    'system follows the OS (light)' => ['none', '0', 'system', false, 'system'],
    'configured default' => ['none', '1', 'light', false, 'light'],
    'invalid stored value ignored' => ['purple', '0', 'dark', true, 'dark'],
]);

it('switches and persists appearance, and refuses invalid modes', function (): void {
    $result = nodeJson('tests/Build/appearance-harness.mjs', ['none', '0', 'system', 'true']);

    expect($result['afterSet'])->toBe(['dark' => true, 'mode' => 'dark', 'stored' => 'dark'])
        ->and($result['accepted'])->toBeTrue()
        ->and($result['rejected'])->toBeFalse()
        ->and($result['events'])->toBe(['gcego-appearance:dark'])
        ->and($result['listensForNavigation'])->toBeTrue();
});

it('ignores storage when switching is disabled', function (): void {
    $result = nodeJson('tests/Build/appearance-harness.mjs', ['dark', '0', 'light', 'false']);

    expect($result['initial']['mode'])->toBe('light')
        ->and($result['accepted'])->toBeFalse()
        ->and($result['afterSet']['stored'])->toBe('dark');
});

it('applies a stored main color before paint, switches and resets it, and refuses colors not offered', function (): void {
    $result = nodeJson('tests/Build/accent-harness.mjs', ['blue', 'teal blue red']);

    expect($result['initial'])->toBe(['accent' => 'blue', 'attribute' => 'blue'])
        ->and($result['red'])->toBeTrue()
        ->and($result['afterRed'])->toBe(['accent' => 'red', 'attribute' => 'red', 'stored' => 'red'])
        ->and($result['unknown'])->toBeFalse()
        ->and($result['reset'])->toBeTrue()
        ->and($result['afterReset'])->toBe(['accent' => null, 'attribute' => null, 'stored' => null])
        ->and($result['events'])->toBe(['gcego-accent:red', 'gcego-accent:null']);
});

it('ignores a stored main color that is no longer offered', function (): void {
    expect(nodeJson('tests/Build/accent-harness.mjs', ['violet', 'teal blue'])['initial'])->toBe(['accent' => null, 'attribute' => null]);
});
