<?php

declare(strict_types=1);

use Gcego\Pages\Presets\PagePresets;
use Illuminate\Support\Facades\Process;

/**
 * The real Tailwind CSS v4 build of gcego/chroma's stylesheet (which scans
 * every package's views and presets, this one's included).
 */
it('compiles every class the page views use, within the stylesheet budget', function (): void {
    $styling = dirname(__DIR__, 3).'/chroma';

    if (trim((string) shell_exec('command -v node')) === '' || ! is_dir(dirname($styling, 2).'/node_modules/@tailwindcss/node')) {
        $this->markTestSkipped('Node.js or the monorepo\'s Tailwind CSS v4 packages are not available.');
    }

    $result = Process::path($styling)->run(['node', 'tests/Build/compile.mjs', 'resources/css/build-entry.css']);
    $build = json_decode($result->output(), true);
    $css = is_array($build) && is_string($build['css'] ?? null) ? $build['css'] : '';
    $selector = static fn (string $class): string => '.'.preg_replace('/([^a-zA-Z0-9_-])/', '\\\\$1', $class);
    $classes = [];

    foreach ((new ReflectionClass(PagePresets::class))->getConstants() as $value) {
        foreach (is_array($value) ? $value : [] as $class) {
            $classes = [...$classes, ...explode(' ', (string) $class)];
        }
    }

    $missing = array_values(array_filter(array_unique([...$classes, 'min-w-0', 'truncate', 'sr-only']), static fn (string $class): bool => ! str_contains($css, $selector($class))));
    recordPerformance(['stylesheet_minified_bytes' => strlen($css), 'stylesheet_gzip_bytes' => strlen((string) gzencode($css, 9))]);

    expect($result->successful())->toBeTrue()
        ->and($missing)->toBe([])
        // The whole framework's stylesheet (see gcego/chroma's TailwindBuildTest for the breakdown).
        ->and(strlen($css))->toBeLessThan(76 * 1024)
        ->and(strlen((string) gzencode($css, 9)))->toBeLessThan(13 * 1024);
});
