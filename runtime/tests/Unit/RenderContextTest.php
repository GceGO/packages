<?php

declare(strict_types=1);

use Gcego\Components\Component;
use Gcego\Components\Properties\Property;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Runtime\Exceptions\InvalidRenderContextException;
use Gcego\Runtime\Rendering\RenderContext;
use Gcego\Runtime\Rendering\RenderMode;

it('describes the root occurrence', function (): void {
    $root = new Component(new Property(Component::Name, 'dashboard'));
    $context = RenderContext::root($root, identity: 'blueprint:dashboard', locale: 'fr_CM', panel: 'admin', theme: 'dark', metadata: ['compact' => true]);

    expect($context->component())->toBe($root)
        ->and($context->path())->toBe('dashboard')
        ->and($context->depth())->toBe(0)
        ->and($context->identity)->toBe('blueprint:dashboard')
        ->and($context->locale)->toBe('fr_CM')
        ->and($context->panel)->toBe('admin')
        ->and($context->metadata)->toBe(['compact' => true])
        ->and($context->isLivewire())->toBeFalse()
        ->and($context->headingLevel)->toBe(1);
});

it('derives child contexts with paths, levels and ancestry', function (): void {
    $child = new Component(new Property(Component::Name, 'stats'));
    $root = new Component(new Property(Component::Name, 'dashboard'), $child);
    $context = RenderContext::root($root, mode: RenderMode::Livewire);
    $node = (new ComponentWalker)->childNodes($context->node)[0];
    $childContext = $context->child($node, $context->nextHeadingLevel());

    expect($childContext->path())->toBe('dashboard.stats')
        ->and($childContext->headingLevel)->toBe(2)
        ->and($childContext->hasAncestor($root))->toBeTrue()
        ->and($childContext->hasAncestor($child))->toBeFalse()
        ->and($childContext->isLivewire())->toBeTrue()
        ->and($context->child($node, 9)->headingLevel)->toBe(6);
});

it('generates stable, unique DOM ids', function (): void {
    $a = RenderContext::root(new Component(new Property(Component::Name, 'a-b')));
    $b = RenderContext::root(new Component(new Property(Component::Name, 'a_b')));

    expect($a->domId())->toBe(RenderContext::root(new Component(new Property(Component::Name, 'a-b')))->domId())
        ->and($a->domId())->not->toBe($b->domId())
        ->and($a->domId('title'))->toBe($a->domId().'-title')
        ->and($a->domId())->toMatch('/^gcego-[a-z0-9-]+$/');
});

it('only exposes wire:model targets inside Livewire', function (): void {
    $static = RenderContext::root(new Component(new Property(Component::Name, 'x')));
    $live = RenderContext::root(new Component(new Property(Component::Name, 'x')), mode: RenderMode::Livewire, state: ['x' => ['query' => 'maths']]);

    expect($live->wireModel('query'))->toBe('state.x.query')
        ->and($live->state())->toBe(['query' => 'maths'])
        ->and($static->state())->toBe([])
        ->and(fn () => $static->wireModel('query'))->toThrow(InvalidRenderContextException::class, 'outside a Livewire host');
});

it('rejects invalid context values', function (array $arguments, string $message): void {
    expect(fn () => RenderContext::root(new Component, ...$arguments))->toThrow(InvalidRenderContextException::class, $message);
})->with([
    [['headingLevel' => 0], 'headingLevel'],
    [['headingLevel' => 7], 'headingLevel'],
    [['panel' => 'Admin Panel'], 'panel'],
    [['theme' => '../../etc'], 'theme'],
    [['locale' => '<script>'], 'locale'],
    [['metadata' => ['user' => new stdClass]], 'metadata'],
]);
