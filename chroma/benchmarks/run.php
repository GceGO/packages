<?php

declare(strict_types=1);

/**
 * Styling benchmark: php benchmarks/run.php [iterations]
 *
 * Measures merging, preset lookup, theme CSS generation and slot resolution
 * (cold and cached) for hundreds of configured components. Pure PHP, no Laravel boot.
 */

use Gcego\Chroma\ChromaConfig;
use Gcego\Chroma\Classes\ClassMerger;
use Gcego\Chroma\Presets\PresetRegistry;
use Gcego\Chroma\Slot;
use Gcego\Chroma\Slots\SlotDefinition;
use Gcego\Chroma\Slots\SlotRegistry;
use Gcego\Chroma\Slots\SlotResolver;
use Gcego\Chroma\Tailwind;
use Gcego\Chroma\Tests\Fixtures\ExampleCard;
use Gcego\Chroma\Themes\Theme;
use Gcego\Chroma\Themes\ThemeRegistry;

require dirname(__DIR__).'/vendor/autoload.php';

$iterations = max(1, (int) ($argv[1] ?? 500));

/**
 * @return array{total: float, per: float}
 */
function measure(int $count, Closure $callback): array
{
    $start = hrtime(true);

    for ($index = 0; $index < $count; $index++) {
        $callback($index);
    }

    $total = (hrtime(true) - $start) / 1e6;

    return ['total' => $total, 'per' => $total / $count * 1000];
}

$merger = new ClassMerger;
$presets = PresetRegistry::withDefaults();
$themes = new ThemeRegistry;
$themes->register(new Theme('ocean', ['primary' => '#0e7490', 'radius' => '1rem'], ['primary' => '#22d3ee'], [new Slot('surface.card', ['rounded-3xl'])]));
$slots = new SlotRegistry;
$slots->register(ExampleCard::class, [
    new SlotDefinition(Tailwind::Wrapper, 'surface.card'),
    new SlotDefinition(Tailwind::Header, 'layout.header', ['border-b', 'px-6', 'py-4']),
    new SlotDefinition(Tailwind::Title, 'typography.heading'),
    new SlotDefinition(Tailwind::Body, classes: ['p-6']),
]);
$resolver = new SlotResolver($slots, $presets, $themes, ChromaConfig::fromArray([]), $merger);

$cards = array_map(
    static fn (int $index): ExampleCard => new ExampleCard('Card '.$index, new Tailwind(
        'ring-1',
        new Slot(Tailwind::Body, ['grid', 'gap-4', 'md:grid-cols-'.(($index % 4) + 1)]),
        new Slot(Tailwind::Header, ['px-8', 'hover:bg-gcego-surface-muted']),
    )),
    range(1, 300),
);
$slotNames = [Tailwind::Wrapper, Tailwind::Header, Tailwind::Title, Tailwind::Body];
$base = explode(' ', 'rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-700 shadow-sm md:p-6 hover:bg-slate-50 focus-visible:outline-2');
$override = explode(' ', 'rounded-2xl p-6 text-base shadow-none md:p-8 dark:bg-slate-900');

$results = [
    'merge (10 base + 6 overrides)' => measure($iterations * 10, static fn () => $merger->merge($base, $override)),
    'preset lookup' => measure($iterations * 10, static fn (int $index) => $presets->get(['surface.card', 'control.button', 'data.table.cell', 'layout.grid'][$index % 4])),
    'theme css' => measure($iterations, static fn () => $themes->get('ocean')->css()),
    'resolve: 300 components x 4 slots' => measure($iterations > 50 ? 20 : 5, static function () use ($resolver, $cards, $slotNames): void {
        foreach ($cards as $card) {
            foreach ($slotNames as $slot) {
                $resolver->resolve($card, $slot, 'ocean');
            }
        }
    }),
];

$cold = new SlotResolver($slots, $presets, $themes, ChromaConfig::fromArray([]), new ClassMerger);
$results['resolve: first (cold cache) call'] = measure(1, static fn () => $cold->resolve($cards[0], Tailwind::Wrapper));

printf("gcego/chroma benchmark, PHP %s, %d iterations\n\n", PHP_VERSION, $iterations);
printf("%-38s %12s %14s\n", 'case', 'total ms', 'per call µs');

foreach ($results as $case => ['total' => $total, 'per' => $per]) {
    printf("%-38s %12.2f %14.2f\n", $case, $total, $per);
}

printf("\npeak memory: %.1f MB\n", memory_get_peak_usage(true) / 1048576);
