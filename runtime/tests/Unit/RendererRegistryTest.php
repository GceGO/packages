<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Blueprints\Contracts\BlueprintPart;
use Gcego\Components\Component;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Runtime\Exceptions\RendererConflictException;
use Gcego\Runtime\Exceptions\RendererNotFoundException;
use Gcego\Runtime\Rendering\BlueprintRenderer;
use Gcego\Runtime\Rendering\ContainerRenderer;
use Gcego\Runtime\Rendering\RendererRegistry;
use Gcego\Runtime\Tests\Fixtures\CountingRenderer;
use Gcego\Runtime\Tests\Fixtures\CyclicComponent;
use Gcego\Runtime\Tests\Fixtures\ExampleHeading;
use Gcego\Runtime\Tests\Fixtures\ExampleTable;
use Gcego\Runtime\Tests\Fixtures\HeadingRenderer;
use Gcego\Runtime\Tests\Fixtures\ParagraphRenderer;
use Gcego\Runtime\Tests\Fixtures\TableRenderer;
use Gcego\Runtime\Tests\Fixtures\TrustedHtmlRenderer;
use Gcego\Runtime\Tests\Fixtures\UnrenderedComponent;
use Illuminate\Contracts\Config\Repository;

it('ships renderers for blueprints and the generic component', function (): void {
    $registry = app(RendererRegistry::class);

    expect($registry->for(new Blueprint('subjects')))->toBeInstanceOf(BlueprintRenderer::class)
        ->and($registry->for(new Component))->toBeInstanceOf(ContainerRenderer::class);
});

it('does not let the generic renderer swallow unknown subclasses', function (): void {
    expect(app(RendererRegistry::class)->has(UnrenderedComponent::class))->toBeFalse()
        ->and(fn () => app(RendererRegistry::class)->for(new UnrenderedComponent('x'), 'root.x'))
        ->toThrow(RendererNotFoundException::class, 'No renderer is registered for '.UnrenderedComponent::class.' (type "fixtures.unrendered") at "root.x".');
});

it('resolves exact classes, then parent classes, then interfaces', function (): void {
    $registry = new RendererRegistry(app());
    $registry->register(ComponentInterface::class, ParagraphRenderer::class);
    $registry->register(Component::class, HeadingRenderer::class);
    $registry->register(ExampleTable::class, TableRenderer::class);

    expect($registry->bindingFor(ExampleTable::class)?->rendererClass())->toBe(TableRenderer::class)
        ->and($registry->bindingFor(ExampleHeading::class)?->rendererClass())->toBe(HeadingRenderer::class)
        ->and($registry->bindingFor(CyclicComponent::class)?->rendererClass())->toBe(ParagraphRenderer::class);
});

it('uses the first registered interface binding', function (): void {
    $registry = new RendererRegistry(app());
    $registry->register(BlueprintPart::class, TableRenderer::class);
    $registry->register(ComponentInterface::class, ParagraphRenderer::class);

    expect($registry->bindingFor(ExampleTable::class)?->rendererClass())->toBe(TableRenderer::class);
});

it('rejects conflicting registrations unless overriding explicitly', function (): void {
    $registry = new RendererRegistry(app());
    $registry->register(ExampleTable::class, TableRenderer::class);
    $registry->register(ExampleTable::class, TableRenderer::class);

    expect(fn () => $registry->register(ExampleTable::class, ParagraphRenderer::class))
        ->toThrow(RendererConflictException::class, TableRenderer::class.' already renders '.ExampleTable::class.'; '.ParagraphRenderer::class.' cannot replace it. Pass override: true');

    $registry->register(ExampleTable::class, ParagraphRenderer::class, override: true);

    expect($registry->bindingFor(ExampleTable::class)?->rendererClass())->toBe(ParagraphRenderer::class)
        ->and($registry->overrides())->toBe([['target' => ExampleTable::class, 'previous' => TableRenderer::class, 'current' => ParagraphRenderer::class]]);
});

it('rejects invalid targets and renderers', function (): void {
    $registry = new RendererRegistry(app());

    expect(fn () => $registry->register(stdClass::class, TableRenderer::class))->toThrow(RendererConflictException::class, 'must be an existing class or interface implementing')
        ->and(fn () => $registry->register(ExampleTable::class, stdClass::class))->toThrow(RendererConflictException::class, 'implementing Gcego\Runtime\Contracts\RendererInterface');
});

it('builds each renderer once through the container', function (): void {
    $registry = new RendererRegistry(app());
    $registry->register(ExampleTable::class, CountingRenderer::class);
    $first = $registry->for(new ExampleTable('a'));

    for ($index = 0; $index < 100; $index++) {
        $registry->for(new ExampleTable('t'.$index));
    }

    expect(CountingRenderer::$instances)->toBe(1)
        ->and($first)->toBeInstanceOf(CountingRenderer::class)
        ->and($first instanceof CountingRenderer ? $first->config : null)->toBeInstanceOf(Repository::class);
});

it('accepts renderer instances and lists bindings', function (): void {
    $registry = new RendererRegistry(app());
    $renderer = new TrustedHtmlRenderer;
    $registry->register(BlueprintInterface::class, $renderer);

    expect($registry->for(new Blueprint('x')))->toBe($renderer)
        ->and($registry->bindings()[0]->toArray())->toBe(['target' => BlueprintInterface::class, 'renderer' => $renderer::class, 'inherit' => true]);
});
