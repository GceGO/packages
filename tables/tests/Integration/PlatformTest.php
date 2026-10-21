<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\Slot;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Properties\Property;
use Gcego\Data\Source;
use Gcego\Data\Support\Callback;
use Gcego\Registry\Registry;
use Gcego\Runtime\Exceptions\RenderException;
use Gcego\Runtime\Rendering\RendererRegistry;
use Gcego\Runtime\Rendering\RenderMode;
use Gcego\Runtime\Runtime;
use Gcego\Security\Requirements\Ability;
use Gcego\Tables\Columns\Text;
use Gcego\Tables\Diagnostics\TableInspector;
use Gcego\Tables\Filters\SelectFilter;
use Gcego\Tables\Livewire\TableHost;
use Gcego\Tables\Table;
use Gcego\Tables\TablesServiceProvider;
use Gcego\Tables\Tests\Fixtures\Blueprints;
use Gcego\Tables\Tests\Fixtures\Models\Subject;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->world = world();
    actAs($this->world['editor']);
});

it('registers renderers, Livewire, styling slots, the package and its publish tags', function (): void {
    expect(app(RendererRegistry::class)->has(new Table(new Text('a'))))->toBeTrue()
        ->and(app(RendererRegistry::class)->has(new Text('a')))->toBeTrue()
        ->and(app(RendererRegistry::class)->has(new SelectFilter('s', new Property(SelectFilter::Options, ['a' => 'A']))))->toBeTrue()
        ->and(app(Gcego\Core\Gcego::class)->hasPackage('gcego/tables'))->toBeTrue()
        ->and(Livewire::test(TablesServiceProvider::LivewireAlias, ['definition' => 'simple'])->instance())->toBeInstanceOf(TableHost::class)
        ->and(app(ChromaManager::class)->classes(new Table(new Text('a')), Tailwind::Wrapper))->toContain('rounded-gcego-lg')
        ->and(ServiceProvider::pathsToPublish(TablesServiceProvider::class, 'gcego-tables-migrations'))->not->toBeEmpty()
        ->and(config('gcego-tables.pagination.max_per_page'))->toBe(100);
});

it('lets instance Tailwind slots restyle the table', function (): void {
    $table = new Table(new Text('name'), new Tailwind(new Slot(Tailwind::Wrapper, ['rounded-2xl', 'border-slate-200']), new Slot(Table::Row, ['hover:bg-slate-50'])));

    expect(app(ChromaManager::class)->classes($table, Tailwind::Wrapper))->toContain('rounded-2xl')
        ->and(app(ChromaManager::class)->classes($table, Table::Row))->toContain('hover:bg-slate-50');
});

it('mounts the Livewire host when a blueprint renders through the runtime', function (): void {
    $html = app(Runtime::class)->render(Blueprints::simple(), RenderMode::Livewire, identity: 'blueprint:simple')->toHtml();

    expect($html)->toContain('wire:name="gcego-table"')
        ->and($html)->toContain('Algebra');
});

it('refuses to render a bare table without a trusted source', function (): void {
    expect(fn () => app(Runtime::class)->render(new Table(new Text('name')))->toHtml())->toThrow(RenderException::class, 'renders inside a registered blueprint');
});

it('registers services and constructs tables without queries', function (): void {
    expect(queriesDuring(function (): void {
        (new TablesServiceProvider(app()))->register();
        Blueprints::subjects();
        tables()->schema(Blueprints::table());
    }))->toBe([]);
});

it('inspects tables without record values', function (): void {
    $report = app(TableInspector::class)->inspect('subjects');

    expect($report->isHealthy())->toBeTrue()
        ->and(array_column($report->columns, 'name'))->toContain('category.name')
        ->and($report->actions['row'])->toContain('edit')
        ->and($report->actions['bulk'])->toBe(['bulk-delete'])
        ->and($report->checks())->toContain('performance')
        ->and((string) json_encode($report->toArray()))->not->toContain('TOP SECRET');
});

it('reports broken tables', function (): void {
    app(Registry::class)->register(new Blueprint('broken', new Source(Subject::class), new Table(
        new Text('api_token', 'Token'),
        new Text('missing.name', 'Missing'),
        new SelectFilter('level', new Property(SelectFilter::Options, new Callback(fn () => []))),
    )));
    $report = app(TableInspector::class)->inspect('broken');

    expect($report->isHealthy())->toBeFalse()
        ->and($report->checks())->toContain('hidden', 'relations', 'authorization', 'performance')
        ->and(app(TableInspector::class)->inspect(new Table(new Text('a'), new Text('a')))->errors()[0]->check)->toBe('schema')
        ->and(app(TableInspector::class)->inspect('nope')->isHealthy())->toBeFalse();
});

it('keeps search, filters, sorting and page in the URL when enabled', function (): void {
    app(Registry::class)->register(new Blueprint('linked', new Ability('viewAny', Subject::class), new Source(Subject::class), new Table(
        new Text('name', new Property(Text::Searchable, true), new Property(Text::Sortable, true)),
        new Property(Table::UrlState, true),
        new Property(Table::Name, 'subjects'),
    )));

    $host = Livewire::withQueryParams(['subjects_search' => 'chem', 'subjects_sort' => '-name'])->test(TableHost::class, ['definition' => 'linked']);

    expect($host->get('search'))->toBe('chem')
        ->and($host->get('sort'))->toBe('-name')
        ->and($host->html())->toContain('Chemistry')
        ->and($host->html())->not->toContain('Biology');

    $tampered = Livewire::withQueryParams(['subjects_sort' => 'api_token'])->test(TableHost::class, ['definition' => 'linked']);

    expect($tampered->html())->toContain('were not valid and were reset');
});
