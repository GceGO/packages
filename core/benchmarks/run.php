<?php

declare(strict_types=1);

/**
 * Repeatable micro-benchmark for gcego/core.
 *
 * Usage: composer benchmark  (or: php benchmarks/run.php [iterations] [packages])
 *
 * Measures, per operation:
 *  - provider registration, boot and automatic framework boot;
 *  - framework boot with N explicitly registered packages;
 *  - dependency resolution of N packages;
 *  - container resolution of the Gcego entry point.
 */

use Gcego\Core\Bootstrap\FrameworkBootstrapper;
use Gcego\Core\Extensions\ExtensionManager;
use Gcego\Core\Gcego;
use Gcego\Core\GcegoServiceProvider;
use Gcego\Core\Lifecycle\LifecycleManager;
use Gcego\Core\Packages\PackageDependencyValidator;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Core\Packages\PackageManager;
use Gcego\Core\Tests\Fixtures\FixturePackage;
use Illuminate\Foundation\Application as LaravelApplication;
use Orchestra\Testbench\Foundation\Application;

require __DIR__.'/../vendor/autoload.php';

$iterations = max(1, (int) ($argv[1] ?? 20));
$packageCount = max(2, (int) ($argv[2] ?? 25));

/**
 * @param  array<int, class-string>  $providers
 */
function createApplication(array $providers): LaravelApplication
{
    return Application::create(
        options: ['extra' => ['providers' => $providers, 'dont-discover' => ['*']], 'load_environment_variables' => false],
    );
}

/**
 * @return list<PackageDescriptor>
 */
$descriptors = static function (int $count): array {
    $descriptors = [];

    for ($index = 0; $index < $count; $index++) {
        $requires = $index === 0 ? [] : ['bench/package-'.intdiv($index - 1, 2)];
        $descriptors[] = new PackageDescriptor('bench/package-'.$index, '1.0.0', $requires, capabilities: ['bench']);
    }

    return array_reverse($descriptors);
};

/**
 * @param  Closure(mixed): mixed  $operation  Receives the setup result.
 * @param  (Closure(): mixed)|null  $setup  Runs before each iteration, untimed.
 */
$measure = static function (string $label, Closure $operation, ?Closure $setup = null) use ($iterations): float {
    $total = 0;

    for ($iteration = 0; $iteration <= $iterations; $iteration++) {
        $prepared = $setup?->__invoke();

        $start = hrtime(true);
        $operation($prepared);
        $elapsed = hrtime(true) - $start;

        if ($iteration > 0) {
            $total += $elapsed;
        }
    }

    $milliseconds = $total / 1e6 / $iterations;
    printf("%-44s %9.3f ms/op\n", $label, $milliseconds);

    return $milliseconds;
};

printf("gcego/core benchmark: %d iterations, %d packages, PHP %s, Laravel %s\n\n", $iterations, $packageCount, PHP_VERSION, LaravelApplication::VERSION);

// Registering on an already-booted app runs register(), boot() and the auto-boot callback in one call.
$measure('provider register + boot + auto-boot', static function (mixed $app): void {
    if ($app instanceof LaravelApplication) {
        $app->register(GcegoServiceProvider::class);
    }
}, static fn (): LaravelApplication => createApplication([]));

$app = createApplication([GcegoServiceProvider::class]);

$measure(sprintf('boot with %d registered packages', $packageCount), static function (mixed $bootstrapper): void {
    if ($bootstrapper instanceof FrameworkBootstrapper) {
        $bootstrapper->boot();
    }
}, static function () use ($app, $descriptors, $packageCount): FrameworkBootstrapper {
    $lifecycle = new LifecycleManager;
    $lifecycle->markRegistered();
    $packages = new PackageManager;

    foreach ($descriptors($packageCount) as $descriptor) {
        $packages->register(new FixturePackage($descriptor));
    }

    return new FrameworkBootstrapper($app, $lifecycle, $packages, new ExtensionManager);
});

$validator = new PackageDependencyValidator;
$small = $descriptors($packageCount);
$large = $descriptors($packageCount * 10);
$measure(sprintf('dependency resolution, %d packages', $packageCount), static fn () => $validator->resolve($small));
$measure(sprintf('dependency resolution, %d packages', $packageCount * 10), static fn () => $validator->resolve($large));

$measure('container resolution of Gcego', static fn () => $app->make(Gcego::class), static fn () => $app->forgetInstance(Gcego::class));

printf("\nPeak memory: %.1f MB\n", memory_get_peak_usage(true) / 1048576);
