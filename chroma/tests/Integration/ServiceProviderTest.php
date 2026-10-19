<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Chroma\ChromaConfig;
use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\ChromaServiceProvider;
use Gcego\Chroma\Classes\ClassMerger;
use Gcego\Chroma\Diagnostics\ChromaInspector;
use Gcego\Chroma\Presets\PresetRegistry;
use Gcego\Chroma\Slot;
use Gcego\Chroma\Slots\SlotRegistry;
use Gcego\Chroma\Slots\SlotResolver;
use Gcego\Chroma\Tailwind;
use Gcego\Chroma\Tests\Fixtures\ExampleCard;
use Gcego\Chroma\Themes\ThemeRegistry;
use Gcego\Components\Component;
use Gcego\Core\Gcego;
use Gcego\Swatches\SwatchesManager;
use Gcego\Swatches\Themes\Theme as ColorTheme;
use Illuminate\Support\ServiceProvider;

it('binds shared, framework-wide services', function (string $service): void {
    expect(app($service))->toBeInstanceOf($service)
        ->and(app($service))->toBe(app($service));
})->with([ChromaManager::class, SlotResolver::class, SlotRegistry::class, PresetRegistry::class, ThemeRegistry::class, ChromaConfig::class, ClassMerger::class]);

it('takes part in the gcego/core package lifecycle', function (): void {
    expect(app(Gcego::class)->bootResult()?->packages)->toBe(['gcego/core', 'gcego/registry', 'gcego/runtime', 'gcego/swatches', 'gcego/chroma'])
        ->and(app(Gcego::class)->hasCapability('styling.tailwind'))->toBeTrue();
});

it('publishes its configuration and registers its view namespace', function (): void {
    expect(ServiceProvider::pathsToPublish(ChromaServiceProvider::class, 'gcego-chroma-config'))->toHaveCount(1)
        ->and(view()->exists('gcego-chroma::components.appearance'))->toBeTrue();
});

it('declares slots for the runtime built-in renderers', function (): void {
    expect(app(SlotRegistry::class)->registrations())->toMatchArray([
        BlueprintInterface::class => ['root', 'header', 'title', 'description', 'body'],
        Component::class => ['root', 'title', 'description'],
    ]);
});

it('does not read env() at runtime and keeps no static state', function (): void {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src', FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());

        expect(array_filter(token_get_all($source), fn (mixed $token): bool => is_array($token) && $token[0] === T_STRING && $token[1] === 'env'))->toBe([], $file->getPathname())
            ->and($source)->not->toMatch('/(private|protected|public)\s+static\s+(?!function)/');
    }
});

it('exposes a healthy inspector report', function (): void {
    $report = app(ChromaInspector::class)->inspect(new Blueprint('subjects', new ExampleCard('A')));

    expect($report->isHealthy())->toBeTrue()
        // Two modes for each gcego/swatches theme (gcego, midnight, slate, ocean, forest).
        ->and($report->check('contrast'))->toHaveCount(10)
        ->and($report->resolved[1]['slots']['body'])->toBe('p-6')
        ->and($report->toArray())->toHaveKeys(['healthy', 'diagnostics', 'resolved']);
});

it('reports low-contrast themes and unknown slots', function (): void {
    app(SwatchesManager::class)->registerTheme(new ColorTheme('pale', ['muted-foreground' => '#cbd5e1']));
    $card = new ExampleCard('A', new Tailwind(new Slot('footer', 'p-2')));

    $report = app(ChromaInspector::class)->inspect($card);

    expect($report->isHealthy())->toBeFalse()
        ->and(implode(' ', array_map(fn ($d): string => $d->message, $report->check('contrast'))))->toContain('Theme "pale" (light): muted-foreground on background is 1.38:1')
        ->and($report->check('target.unknown-slot')[0]->details['slot'])->toBe('footer');
});
