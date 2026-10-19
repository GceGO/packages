<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Core\Gcego;
use Gcego\Registry\Registry;
use Gcego\Runtime\Contracts\RenderAuthorizer;
use Gcego\Runtime\Contracts\StylingResolver;
use Gcego\Runtime\Diagnostics\RuntimeInspector;
use Gcego\Runtime\Livewire\BlueprintHost;
use Gcego\Runtime\Rendering\ComponentRenderer;
use Gcego\Runtime\Rendering\RendererRegistry;
use Gcego\Runtime\Runtime;
use Gcego\Runtime\RuntimeConfig;
use Gcego\Runtime\RuntimeServiceProvider;
use Gcego\Runtime\Security\GateRenderAuthorizer;
use Gcego\Runtime\State\StateCoordinator;
use Gcego\Runtime\Styling\NullStylingResolver;
use Gcego\Runtime\Tests\Fixtures\ExampleParagraph;
use Gcego\Runtime\Tests\Fixtures\ExampleTable;
use Gcego\Runtime\Tests\Fixtures\FixtureStylingResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

it('binds its services', function (string $abstract, string $concrete): void {
    expect(app($abstract))->toBeInstanceOf($concrete)
        ->and(app($abstract))->toBe(app($abstract));
})->with([
    [Runtime::class, Runtime::class],
    [RendererRegistry::class, RendererRegistry::class],
    [ComponentRenderer::class, ComponentRenderer::class],
    [RuntimeConfig::class, RuntimeConfig::class],
    [StateCoordinator::class, StateCoordinator::class],
    [StylingResolver::class, NullStylingResolver::class],
    [RenderAuthorizer::class, GateRenderAuthorizer::class],
]);

it('lets packages replace the styling resolver and authorizer', function (): void {
    app()->singleton(StylingResolver::class, FixtureStylingResolver::class);

    expect(app(StylingResolver::class))->toBeInstanceOf(FixtureStylingResolver::class);
});

it('registers the view namespace, publishing and the Livewire host', function (): void {
    expect(view()->exists('gcego-runtime::renderers.blueprint'))->toBeTrue()
        ->and(Livewire::exists('gcego-blueprint'))->toBeTrue()
        ->and(ServiceProvider::pathsToPublish(RuntimeServiceProvider::class, 'gcego-runtime-views'))->toHaveCount(1)
        ->and(ServiceProvider::pathsToPublish(RuntimeServiceProvider::class, 'gcego-runtime-config'))->toHaveCount(1);
});

it('can disable or rename the Livewire host', function (): void {
    $this->rebuildApplication(['gcego-runtime.livewire.alias' => 'admin-blueprint']);

    expect(Livewire::exists('admin-blueprint'))->toBeTrue();
});

it('takes part in the gcego/core package lifecycle', function (): void {
    expect(app(Gcego::class)->bootResult()?->packages)->toBe(['gcego/core', 'gcego/registry', 'gcego/runtime'])
        ->and(app(Gcego::class)->hasCapability('runtime.livewire'))->toBeTrue();
});

it('lets applications override package views', function (): void {
    $override = resource_path('views/vendor/gcego-runtime/renderers/blueprint.blade.php');
    @mkdir(dirname($override), 0777, true);
    file_put_contents($override, '<article data-overridden>{{ $title }}</article>');

    try {
        $this->rebuildApplication();

        expect(app(Runtime::class)->render(new Blueprint('subjects'))->toHtml())->toBe('<article data-overridden>Subjects</article>');
    } finally {
        unlink($override);
        @rmdir(dirname($override));
        @rmdir(dirname($override, 2));
    }
});

it('renders registered definitions from Blade after authorization', function (): void {
    app(Registry::class)->register(new Blueprint('subjects'));
    Gate::define('gcego-runtime.render', fn (?object $user, string $identity): bool => $identity === 'blueprint:subjects');

    expect(Blade::render('<x-gcego-runtime::render target="subjects" />'))->toContain('>Subjects</h1>');

    app(Registry::class)->register(new Blueprint('secret'));

    expectRootCause(fn () => Blade::render('<x-gcego-runtime::render target="secret" />'), AuthorizationException::class);
});

it('does not read env() at runtime and keeps no static state', function (): void {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src', FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());
        $calls = array_filter(token_get_all($source), fn (mixed $token): bool => is_array($token) && $token[0] === T_STRING && $token[1] === 'env');

        expect($calls)->toBe([], $file->getPathname())
            ->and($source)->not->toMatch('/(private|protected|public)\s+static\s+(?!function)/');
    }
});

it('keeps no request state in shared services across requests', function (): void {
    app(Registry::class)->register(new Blueprint('subjects', new ExampleTable('table', new ExampleParagraph('A'))));
    Route::get('/probe', fn () => app(Runtime::class)->render('subjects')->toHtml());

    $this->get('/probe?user=alice')->assertOk()->assertSee('data-gcego-blueprint="blueprint:subjects"', false);
    $this->get('/probe?user=bob')->assertOk();

    $stack = [app(Runtime::class), app(RendererRegistry::class), app(ComponentRenderer::class)];
    $seen = [];

    while ($stack !== []) {
        $object = array_pop($stack);

        if (isset($seen[spl_object_id($object)]) || $object instanceof Container) {
            continue;
        }

        $seen[spl_object_id($object)] = true;
        expect($object)->not->toBeInstanceOf(Request::class);

        foreach ((array) $object as $value) {
            foreach (is_array($value) ? $value : [$value] as $item) {
                if (is_object($item)) {
                    $stack[] = $item;
                }
            }
        }
    }
});

it('hosts nothing but its own Livewire class', function (): void {
    expect((new ReflectionClass(BlueprintHost::class))->isFinal())->toBeTrue()
        ->and(array_map(fn (ReflectionProperty $property): string => $property->getName(), (new ReflectionClass(BlueprintHost::class))->getProperties(ReflectionProperty::IS_PUBLIC)))
        ->toBe(['blueprint', 'state']);
});

it('exposes a diagnostic inspector', function (): void {
    expect(app(RuntimeInspector::class)->inspect()->isHealthy())->toBeTrue();
});
