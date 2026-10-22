<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\Tailwind;
use Gcego\Infolists\Livewire\InfolistHost;
use Gcego\Pages\Components\Content;
use Gcego\Pages\Components\PageLink;
use Gcego\Pages\Exceptions\PageRenderingException;
use Gcego\Pages\Livewire\PageHost;
use Gcego\Pages\Page;
use Gcego\Pages\PagesConfig;
use Gcego\Pages\PagesServiceProvider;
use Gcego\Pages\Registry\PageKind;
use Gcego\Pages\Tests\Fixtures\Definitions;
use Gcego\Registry\Kinds\DefinitionKinds;
use Gcego\Registry\Registry;
use Gcego\Rendering\Exceptions\RenderException;
use Gcego\Rendering\RenderEngine;
use Gcego\Rendering\Renderers\RendererRegistry;
use Gcego\Security\Exceptions\AccessDeniedException;
use Gcego\Security\Requirements\Authenticated;
use Gcego\Tables\Livewire\TableHost;
use Livewire\Livewire;

it('registers the page kind, renderers, Livewire host, slots and configuration', function (): void {
    $renderers = app(RendererRegistry::class);

    expect(app(DefinitionKinds::class)->get('page'))->toBeInstanceOf(PageKind::class)
        ->and($renderers->has(Definitions::about()))->toBeTrue()
        ->and($renderers->has(new Content('x')))->toBeTrue()
        ->and($renderers->has(new PageLink('x', 'about')))->toBeTrue()
        ->and(Livewire::new('gcego-page'))->toBeInstanceOf(PageHost::class)
        ->and(app(PagesConfig::class)->namePrefix)->toBe('gcego.')
        ->and(app(ChromaManager::class)->classes(Definitions::subjects(), Tailwind::Title))->toBe('')
        ->and(fn () => app(ChromaManager::class)->classes(Definitions::subjects(), 'ribbon'))->toThrow('has no "ribbon" slot')
        ->and((new PagesServiceProvider(app()))->descriptor()->id)->toBe('gcego/pages');
});

it('registers pages as "page" definitions and blueprints as "blueprint"', function (): void {
    $page = app(Registry::class)->register(new Page('runtime', 'Runtime', new Authenticated, new Content('x')));
    $blueprint = app(Registry::class)->register(new Blueprint('plain'));

    expect($page->key())->toBe('page:runtime')
        ->and($blueprint->key())->toBe('blueprint:plain')
        ->and(app(Registry::class)->descriptors('page'))->toHaveCount(12);
});

it('is a blueprint whose parts the existing hosts find by page identity', function (): void {
    $world = world();
    actAs($world['admin']);
    $page = pages()->resolve('subjects');

    expect($page)->toBeInstanceOf(BlueprintInterface::class)
        ->and($page->roles())->toContain(BlueprintRole::Source, BlueprintRole::Table, BlueprintRole::Action);

    Livewire::test(TableHost::class, ['definition' => 'page:subjects'])->assertSee('Chemistry')->assertDontSee('Secret Biology');
    Livewire::test(InfolistHost::class, ['definition' => 'page:subjects-view', 'record' => 1])->assertSee('CHE');
    Livewire::test(InfolistHost::class, ['definition' => 'page:subjects-view', 'record' => 4])->assertNotFound();
});

it('renders a page through gcego/runtime by identity, authorizing it first', function (): void {
    $world = world();
    actAs($world['reader']);

    expect(squish(app(RenderEngine::class)->render('page:about')->toHtml()))->toContain('About GCEGO</h1>');

    try {
        app(RenderEngine::class)->render('page:reports-overview')->toHtml();
        $denied = null;
    } catch (RenderException $exception) {
        $denied = $exception->getPrevious();
    }

    // Never swallowed: the strict runtime rethrows the denial (page routes open the page first and answer 403).
    expect($denied)->toBeInstanceOf(AccessDeniedException::class);
});

it('refuses to render a page without its registry identity', function (): void {
    actAs(world()['admin']);

    try {
        app(RenderEngine::class)->render(Definitions::about())->toHtml();
        $failure = null;
    } catch (RenderException $exception) {
        $failure = $exception->getPrevious();
    }

    expect($failure)->toBeInstanceOf(PageRenderingException::class)
        ->and($failure?->getMessage())->toContain('registry identity');
});

it('mounts the page host only through the page route or a trusted embed', function (): void {
    $world = world();
    actAs($world['admin']);

    Livewire::test(PageHost::class, ['page' => 'subjects.view', 'record' => 1])
        ->assertSee('Chemistry')
        ->assertSet('page', 'page:subjects-view')
        ->assertSet('record', '1');
});
