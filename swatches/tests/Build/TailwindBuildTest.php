<?php

declare(strict_types=1);

use Gcego\Swatches\Tokens\SemanticTokens;

/**
 * Real Tailwind CSS v4 builds through the monorepo's @tailwindcss/node and @tailwindcss/oxide.
 *
 * @return array{css: string, candidates: int, files: list<string>}
 */
function build(bool $minify = true): array
{
    $result = nodeJson('tests/Build/compile.mjs', $minify ? ['resources/css/build-entry.css'] : ['resources/css/build-entry.css', '--no-minify']);

    expect($result['css'] ?? null)->toBeString();

    /** @var array{css: string, candidates: int, files: list<string>} $result */
    return $result;
}

function selector(string $class): string
{
    return '.'.preg_replace('/([^a-zA-Z0-9_-])/', '\\\\$1', $class);
}

/**
 * Whether a rule whose selector list includes $class declares $declaration (Tailwind groups identical rules).
 */
function declares(string $css, string $class, string $declaration): bool
{
    preg_match_all('/([^{}]+)\{([^{}]*)\}/', (string) preg_replace('/\s+/', '', $css), $rules, PREG_SET_ORDER);

    foreach ($rules as [, $selectors, $body]) {
        if (in_array(selector($class), explode(',', $selectors), true) && str_contains($body, $declaration)) {
            return true;
        }
    }

    return false;
}

it('generates the semantic utilities components use', function (string $class, string $declaration): void {
    expect(declares(build(minify: false)['css'], $class, $declaration))->toBeTrue("{$class} should declare {$declaration}");
})->with([
    ['bg-surface', 'background-color:var(--gcego-surface)'],
    ['text-foreground', 'color:var(--gcego-foreground)'],
    ['border-border', 'border-color:var(--gcego-border)'],
    ['bg-primary', 'background-color:var(--gcego-primary)'],
    ['text-primary-foreground', 'color:var(--gcego-primary-foreground)'],
    ['text-muted-foreground', 'color:var(--gcego-muted-foreground)'],
    ['bg-danger', 'background-color:var(--gcego-danger)'],
    ['text-danger-foreground', 'color:var(--gcego-danger-foreground)'],
    ['border-input', 'border-color:var(--gcego-input)'],
    ['bg-sidebar-active', 'background-color:var(--gcego-sidebar-active)'],
    ['fill-chart-1', 'fill:var(--gcego-chart-1)'],
    ['stroke-chart-8', 'stroke:var(--gcego-chart-8)'],
    ['bg-gcego-surface', 'background-color:var(--gcego-surface)'],
    ['text-gcego-text-muted', 'color:var(--gcego-text-muted)'],
]);

it('supports opacity modifiers and the dark variant', function (): void {
    $css = (string) preg_replace('/\s+/', '', build(minify: false)['css']);

    expect($css)->toContain(selector('bg-primary/50'))
        ->and($css)->toContain('color-mix(inoklab,var(--gcego-primary)50%,transparent)')
        ->and($css)->toContain(selector('dark:bg-surface-muted').':where(.dark,.dark*)')
        ->and($css)->toContain(selector('hover:bg-primary-hover').':hover');
});

it('ships light and dark values in the build', function (): void {
    $css = (string) preg_replace('/\s+/', '', build(minify: false)['css']);
    $short = static fn (string $hex): string => (string) preg_replace('/^#([0-9a-f])\1([0-9a-f])\2([0-9a-f])\3$/i', '#$1$2$3', $hex);

    foreach (SemanticTokens::Definitions as $name => [, $light, $dark]) {
        if (! str_starts_with($light, '{') && str_starts_with($light, '#')) {
            expect($css)->toContain("--gcego-{$name}:".$short($light));
        }

        if ($dark !== null && str_starts_with($dark, '#')) {
            expect($css)->toContain("--gcego-{$name}:".$short($dark));
        }
    }

    // Presets are runtime CSS by default (css.static_presets); resources/css/themes.css is opt-in.
    expect($css)->toContain(':root.dark{color-scheme:dark;')
        ->and($css)->not->toContain('data-gcego-theme')
        ->and($css)->not->toContain('--color-surface:');
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
        ->and($result['events'])->toBe(['gcego-appearance:dark']);
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
        ->and($result['afterRed'])->toBe(['accent' => 'red', 'attribute' => 'red', 'stored' => 'red'])
        ->and($result['unknown'])->toBeFalse()
        ->and($result['afterReset'])->toBe(['accent' => null, 'attribute' => null, 'stored' => null]);
});

it('drives switcher buttons and keeps aria-pressed in sync without Alpine', function (): void {
    $result = nodeJson('tests/Build/switcher-harness.mjs');

    expect($result['initial'])->toBe(['light' => 'false', 'dark' => 'false', 'system' => 'true'])
        ->and($result['afterClick'])->toBe(['light' => 'false', 'dark' => 'true', 'system' => 'false'])
        ->and($result['mode'])->toBe('dark')
        ->and($result['dark'])->toBeTrue();
});

it('ships a prebuilt explorer stylesheet that matches its sources', function (): void {
    $result = nodeJson('scripts/build-explorer.mjs', ['--check']);

    expect($result['fresh'])->toBeTrue('resources/dist/explorer.css is stale: run composer build-explorer');
});

it('keeps the explorer out of application builds', function (): void {
    $css = build()['css'];

    expect($css)->not->toContain(selector('grid-cols-[12rem_1fr]'))
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/dist/explorer.css'))->toContain(selector('grid-cols-[12rem_1fr]'));
});
