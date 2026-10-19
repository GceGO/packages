<?php

declare(strict_types=1);

use Gcego\Components\Property;
use Gcego\Data\Source;
use Gcego\Modules\Compilation\ModuleCompiler;
use Gcego\Modules\Exceptions\InvalidModuleException;
use Gcego\Modules\Module;
use Gcego\Modules\Registry\ModuleKind;
use Gcego\Modules\Resource;
use Gcego\Modules\Tests\Fixtures\Extensions\AddGlossary;
use Gcego\Modules\Tests\Fixtures\Extensions\RenameModule;
use Gcego\Modules\Tests\Fixtures\Hooks\RecordingHook;
use Gcego\Modules\Tests\Fixtures\Models\Subject;
use Gcego\Pages\Types\IndexPage;
use Gcego\Registry\Contracts\RegistryInterface;
use Gcego\Registry\Exceptions\DuplicateDefinitionException;
use Gcego\Registry\Exceptions\InvalidDefinitionException;
use Gcego\Registry\Index\SourceType;
use Gcego\Registry\RegistryManager;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Table;

it('discovers Module.php files recursively and registers their pages with the module\'s source', function (): void {
    $registry = app(RegistryInterface::class);
    $module = $registry->descriptor('module:academics');
    $page = $registry->descriptor('page:academics-subjects-edit');

    expect($registry->names(ModuleKind::Id))->toBe(['academics', 'notes'])
        ->and($module->source->type)->toBe(SourceType::File)
        ->and($module->source->location)->toEndWith('Fixtures/Modules/Academics/Module.php')
        ->and($page->source->location)->toBe($module->source->location)
        ->and($module->metadata['title'])->toBe('Academic Management')
        ->and($module->metadata['resources'])->toBe('subjects,topics,tags')
        ->and(json_decode((string) $module->metadata['pages'], true)['academics.subjects.topics'])->toBe(['resource' => 'subjects', 'relation' => 'topics', 'operation' => 'index']);
});

it('loads modules and their pages lazily from a cached index', function (): void {
    $cache = sys_get_temp_dir().'/gcego-modules-'.uniqid().'.php';
    $this->reboot(['gcego-registry.cache.enabled' => true, 'gcego-registry.cache.path' => $cache, 'gcego-registry.cache.verify' => false]);

    try {
        app(RegistryManager::class)->rebuild();
        $this->reboot(['gcego-registry.cache.enabled' => true, 'gcego-registry.cache.path' => $cache, 'gcego-registry.cache.verify' => false]);
        $registry = app(RegistryInterface::class);

        expect($registry->origin())->toBe('cache')
            ->and($registry->get('page:academics-subjects'))->toBeInstanceOf(IndexPage::class)
            ->and(modules()->module('academics')->resources)->toHaveCount(3);
    } finally {
        @unlink($cache);
    }
});

it('registers modules at runtime, with their pages, and rejects duplicates', function (): void {
    $module = new Module('library', new Resource(new Property(Resource::Name, 'books'), new Source(Subject::class), new Table(new Text('name', 'Title'))));

    modules()->register($module);
    modules()->register($module);

    expect(modules()->names())->toContain('library')
        ->and(app(RegistryInterface::class)->has('page:library-books'))->toBeTrue()
        ->and(fn () => modules()->register(new Module('library', new Resource(Subject::class))))->toThrow(DuplicateDefinitionException::class)
        ->and(fn () => modules()->register(new Module('archive', new Resource(new Property(Resource::Name, 'subjects'), new Property(Resource::Slug, 'x'), new Source(Subject::class), new Table(new Text('name', 'Name'))), new IndexPage(new Property(IndexPage::Name, 'subjects'), new Source(Subject::class), new Table(new Text('name', 'Name'))))))->toThrow(InvalidDefinitionException::class, 'declared twice');
});

it('applies trusted extensions in order and keeps the module name', function (): void {
    $this->reboot(['gcego-modules.extensions' => [AddGlossary::class]]);

    expect(app(RegistryInterface::class)->has('page:academics-glossary'))->toBeTrue()
        ->and(array_map(static fn ($page): string => $page->pageName(), modules()->module('academics')->pages))->toContain('academics.glossary');

    // An extension may not rename a module: the registry refuses it while building the index.
    expect(fn () => $this->reboot(['gcego-modules.extensions' => [RenameModule::class]]))->toThrow(InvalidDefinitionException::class, 'it renamed the module');
});

it('rejects extensions that are not ModuleExtensions', function (): void {
    expect(fn () => modules()->extend(stdClass::class))->toThrow(InvalidModuleException::class);
});

it('tells lifecycle hooks about each compilation stage', function (): void {
    RecordingHook::$events = [];
    $this->reboot(['gcego-modules.lifecycle.hooks' => [RecordingHook::class]]);
    app(ModuleCompiler::class)->pages(new Module('library', new Resource(new Property(Resource::Name, 'books'), new Source(Subject::class), new Table(new Text('name', 'Title')))));

    expect(array_values(array_filter(RecordingHook::$events, static fn (string $event): bool => str_starts_with($event, 'library:'))))->toBe(['library:validated', 'library:extended', 'library:normalized', 'library:pages_derived:1']);
});

it('can turn generated pages and discovery off', function (): void {
    $this->reboot(['gcego-modules.resources.auto_pages' => false]);
    expect(app(RegistryInterface::class)->names('page'))->toBe(['academics-overview'])
        ->and(modules()->resource('academics', 'subjects')->operations)->toBe([]);

    $this->reboot(['gcego-modules.discovery.enabled' => false]);
    expect(modules()->names())->toBe([]);
});
