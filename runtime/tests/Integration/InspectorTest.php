<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Registry\Registry;
use Gcego\Runtime\Diagnostics\RuntimeInspector;
use Gcego\Runtime\Rendering\RendererRegistry;
use Gcego\Runtime\Tests\Fixtures\ExampleContainer;
use Gcego\Runtime\Tests\Fixtures\ExampleParagraph;
use Gcego\Runtime\Tests\Fixtures\ParagraphRenderer;
use Gcego\Runtime\Tests\Fixtures\TableRenderer;
use Gcego\Runtime\Tests\Fixtures\UnrenderedComponent;

it('reports renderers, views, the Livewire host and configuration', function (): void {
    $report = app(RuntimeInspector::class)->inspect();

    expect($report->isHealthy())->toBeTrue()
        ->and(array_column($report->renderers, 'target'))->toContain(BlueprintInterface::class)
        ->and($report->check('livewire')[0]->level)->toBe('ok')
        ->and($report->check('views'))->toHaveCount(5)
        ->and($report->check('config')[0]->details['ability'])->toBe('gcego-runtime.render')
        ->and($report->toArray())->toHaveKeys(['healthy', 'renderers', 'missingRenderers', 'diagnostics']);
});

it('finds components without renderers by path', function (): void {
    $report = app(RuntimeInspector::class)->inspect(new ExampleContainer('box', new ExampleParagraph('ok'), new UnrenderedComponent('ghost')));

    expect($report->isHealthy())->toBeFalse()
        ->and($report->missingRenderers)->toBe([['path' => 'box.ghost', 'type' => 'fixtures.unrendered', 'class' => UnrenderedComponent::class]])
        ->and($report->check('target.renderers')[0]->message)->toBe('1 of 3 component(s) have no renderer.');
});

it('inspects registered definitions and reports validation and overrides', function (): void {
    app(Registry::class)->register(new Blueprint('subjects'));
    app(RendererRegistry::class)->register(ExampleParagraph::class, TableRenderer::class, override: true);

    $report = app(RuntimeInspector::class)->inspect('subjects');

    expect($report->check('target.validation')[0]->level)->toBe('ok')
        ->and($report->check('renderers.override')[0]->details['previous'])->toBe(ParagraphRenderer::class);
});

it('never throws for bad targets', function (): void {
    expect(app(RuntimeInspector::class)->inspect('blueprint:missing')->check('target')[0]->level)->toBe('error');
});
