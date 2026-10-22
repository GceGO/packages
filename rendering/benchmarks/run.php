<?php

declare(strict_types=1);

/**
 * Repeatable benchmark for gcego/rendering in a booted Testbench application.
 *
 * Usage: composer benchmark  (or: php benchmarks/run.php [components] [iterations])
 *
 * Measures renderer lookup, rendering a wide nested tree, repeated rendering,
 * render operations through gcego/runtime and memory growth. Blade views are
 * compiled on the first render (reported separately as "first render").
 */

use Gcego\Blueprints\BlueprintsServiceProvider;
use Gcego\Components\ComponentServiceProvider;
use Gcego\Core\GcegoServiceProvider;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\Registry;
use Gcego\Registry\RegistryServiceProvider;
use Gcego\Rendering\RenderEngine;
use Gcego\Rendering\Renderers\RendererRegistry;
use Gcego\Rendering\RenderingServiceProvider;
use Gcego\Rendering\Tests\Fixtures\ExampleContainer;
use Gcego\Rendering\Tests\Fixtures\ExampleParagraph;
use Gcego\Rendering\Tests\Fixtures\ExampleTable;
use Gcego\Rendering\Tests\Fixtures\FixtureKind;
use Gcego\Rendering\Tests\Fixtures\ListContainerRenderer;
use Gcego\Rendering\Tests\Fixtures\ParagraphRenderer;
use Gcego\Rendering\Tests\Fixtures\TableRenderer;
use Gcego\Runtime\Context\ExecutionContext;
use Gcego\Runtime\Runtime;
use Gcego\Runtime\RuntimeServiceProvider;
use Illuminate\Support\Facades\Facade;
use Orchestra\Testbench\Foundation\Application;

require __DIR__.'/../vendor/autoload.php';

$app = new Illuminate\Foundation\Application(dirname(__DIR__));
Facade::setFacadeApplication($app);
$app->register(ComponentServiceProvider::class);

$components = max(10, (int) ($argv[1] ?? 500));
$iterations = max(1, (int) ($argv[2] ?? 20));

$app = Application::create(options: [
    'extra' => [
        'providers' => [GcegoServiceProvider::class, RegistryServiceProvider::class, BlueprintsServiceProvider::class, RuntimeServiceProvider::class, RenderingServiceProvider::class],
        'dont-discover' => ['*'],
    ],
    'load_environment_variables' => false,
]);
$app['config']->set('gcego-registry.discovery.enabled', false);
$app['config']->set('gcego-registry.cache.enabled', false);
$app['config']->set('gcego-blueprints.discovery.enabled', false);
$app['view']->addNamespace('fixtures', __DIR__.'/../tests/Fixtures/views');
$app->make(DefinitionKinds::class)->register(new FixtureKind);

$renderers = $app->make(RendererRegistry::class);
$renderers->register(ExampleParagraph::class, ParagraphRenderer::class);
$renderers->register(ExampleContainer::class, ListContainerRenderer::class);
$renderers->register(ExampleTable::class, TableRenderer::class);
$engine = $app->make(RenderEngine::class);
$runtime = $app->make(Runtime::class);

$perSection = 25;
$sections = [];

for ($section = 0; $section < intdiv($components, $perSection); $section++) {
    $sections[] = new ExampleContainer('s'.$section, ...array_map(static fn (int $index): ExampleParagraph => new ExampleParagraph('Item '.$index), range(1, $perSection)));
}

$tree = new ExampleContainer('root', ...$sections);
$table = new ExampleContainer('subjects', new ExampleTable('table', ...array_map(static fn (int $index): ExampleParagraph => new ExampleParagraph('Row '.$index), range(1, 50))));
$app->make(Registry::class)->register($table);

$measure = static function (string $label, Closure $operation, int $operations = 1) use ($iterations): void {
    $start = hrtime(true);

    for ($iteration = 0; $iteration < $iterations; $iteration++) {
        $operation();
    }

    $elapsed = (hrtime(true) - $start) / 1e6 / $iterations;

    $operations === 1
        ? printf("%-40s %9.3f ms/op\n", $label, $elapsed)
        : printf("%-40s %9.3f ms per %d (%.3f us each)\n", $label, $elapsed, $operations, $elapsed * 1000 / $operations);
};

printf("gcego/rendering benchmark: %d components, %d iterations, PHP %s, Laravel %s\n\n", count($sections) * ($perSection + 1) + 1, $iterations, PHP_VERSION, $app->version());

$start = hrtime(true);
$engine->render($tree)->toHtml();
printf("%-40s %9.3f ms (includes Blade compilation)\n", 'first render of the tree', (hrtime(true) - $start) / 1e6);

$paragraph = new ExampleParagraph('x');
$measure('renderer lookup', static function () use ($renderers, $paragraph): void {
    for ($lookup = 0; $lookup < 10000; $lookup++) {
        $renderers->for($paragraph);
    }
}, 10000);
$measure('render nested tree', static fn () => $engine->render($tree)->toHtml());
$measure('render table (52 components)', static fn () => $engine->render($table)->toHtml());

$system = ExecutionContext::system();
$operation = RenderEngine::operation('fixture:subjects');
$measure('execute a render operation', static fn () => $runtime->execute($operation, $system));

gc_collect_cycles();
$before = memory_get_usage();

for ($iteration = 0; $iteration < 100; $iteration++) {
    $engine->render($table)->toHtml();
    $runtime->execute($operation, $system);
}

gc_collect_cycles();
printf("\nMemory growth over 100 renders and render operations: %.1f KB; peak %.1f MB\n", (memory_get_usage() - $before) / 1024, memory_get_peak_usage(true) / 1048576);
