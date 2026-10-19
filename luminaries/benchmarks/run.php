<?php

declare(strict_types=1);

/**
 * Luminaries benchmark: php benchmarks/run.php [iterations]
 *
 * Boots a minimal Laravel application (Testbench) and measures registry
 * initialization, alias resolution, rendering of many icons and Blade
 * component rendering, with and without the cached icon manifest.
 */

use Gcego\Luminaries\Luminaries;
use Gcego\Luminaries\LuminariesServiceProvider;
use Gcego\Luminaries\Registry\IconRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Facade;
use Illuminate\View\Component;
use Orchestra\Testbench\Foundation\Application;

require dirname(__DIR__).'/vendor/autoload.php';

$iterations = max(1, (int) ($argv[1] ?? 1000));

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

    return ['total' => $total, 'per' => $total / $count];
}

function boot(?string $manifest): Illuminate\Foundation\Application
{
    $app = Application::create(null, null, ['extra' => ['providers' => [LuminariesServiceProvider::class], 'dont-discover' => ['*']]]);
    $app['config']->set('luminaries.cache.path', $manifest);
    $app['config']->set('luminaries.missing.behavior', 'exception');
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($app);
    Component::forgetFactory();
    Component::flushCache();

    return $app;
}

function line(string $label, array $result, int $count): void
{
    printf("%-52s %9.2f ms total  %8.4f ms/op  (%d ops)\n", $label, $result['total'], $result['per'], $count);
}

$manifest = sys_get_temp_dir().'/gcego-luminaries-benchmark.php';
@unlink($manifest);

$aliases = ['actions.edit', 'actions.delete', 'status.success', 'status.danger', 'navigation.dashboard', 'tables.sort', 'forms.help', 'media.image'];
$names = [...$aliases, 'lucide:graduation-cap', 'heroicon-solid:academic-cap', 'check', 'magnifying-glass'];

foreach (['uncached' => null, 'cached manifest' => $manifest] as $mode => $path) {
    echo "\n== {$mode}\n";

    $app = boot($path);

    if ($path !== null) {
        Artisan::call('gcego:icons:cache');
        $app = boot($path);
    }

    $cold = measure(1, static fn () => $app->make(Luminaries::class)->resolve('actions.edit'));
    line('registry init + first resolve (cold)', $cold, 1);

    $luminaries = $app->make(Luminaries::class);

    $fresh = measure(count($names), static function (int $index) use ($app, $names): void {
        $app->forgetInstance(Luminaries::class);
        $app->forgetInstance(IconRegistry::class);
        $app->make(Luminaries::class)->resolve($names[$index]);
    });
    line('rebuild registry + resolve one name', $fresh, count($names));

    $resolve = measure($iterations, static fn (int $index) => $luminaries->resolve($names[$index % count($names)]));
    line('resolve (memoized)', $resolve, $iterations);

    $exists = measure($iterations, static fn (int $index) => $luminaries->exists('lucide:'.['pencil', 'trash', 'plus', 'x', 'nope-'.$index][$index % 5]));
    line('exists() incl. unseen/missing names', $exists, $iterations);

    $render = measure($iterations, static fn (int $index) => $luminaries->render($names[$index % count($names)], size: 'md'));
    line('render() mixed providers', $render, $iterations);

    $search = measure(20, static fn () => $luminaries->search('arrow', limit: 50));
    line('search("arrow") across all providers', $search, 20);

    $blade = measure(10, static fn () => Blade::render('@foreach ($names as $name)<x-gcego-icon :name="$name" />@endforeach', ['names' => array_merge(...array_fill(0, 25, $names))]));
    line(sprintf('Blade page with %d <x-gcego-icon>', 25 * count($names)), $blade, 10);
}

@unlink($manifest);
printf("\nPeak memory: %.1f MB\n", memory_get_peak_usage(true) / 1048576);
