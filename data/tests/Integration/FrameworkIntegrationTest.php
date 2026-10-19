<?php

declare(strict_types=1);

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\BlueprintRole;
use Gcego\Core\Gcego;
use Gcego\Data\Contracts\DataAuthorizer;
use Gcego\Data\DataConfig;
use Gcego\Data\DataManager;
use Gcego\Data\DataServiceProvider;
use Gcego\Data\Definition\SourceCompiler;
use Gcego\Data\Filters\Filter;
use Gcego\Data\Integration\SourceRenderer;
use Gcego\Data\Providers\ProviderRegistry;
use Gcego\Data\Query\QueryContext;
use Gcego\Data\Query\QueryPipeline;
use Gcego\Data\Records\RecordResolver;
use Gcego\Data\Security\GateDataAuthorizer;
use Gcego\Data\Security\ScopeRegistry;
use Gcego\Data\Source;
use Gcego\Data\Tests\Fixtures\Models\Subject;
use Gcego\Registry\Registry;
use Gcego\Runtime\Rendering\RendererRegistry;
use Gcego\Runtime\Runtime;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

it('binds shared services', function (string $service): void {
    expect(app($service))->toBeInstanceOf($service)
        ->and(app($service))->toBe(app($service));
})->with([DataManager::class, SourceCompiler::class, QueryPipeline::class, RecordResolver::class, ProviderRegistry::class, ScopeRegistry::class, DataConfig::class]);

it('registers the built-in providers and the default authorizer', function (): void {
    expect(app(ProviderRegistry::class)->names())->toBe(['eloquent', 'query', 'collection'])
        ->and(app(DataAuthorizer::class))->toBeInstanceOf(GateDataAuthorizer::class)
        ->and(ServiceProvider::pathsToPublish(DataServiceProvider::class, 'gcego-data-config'))->toHaveCount(1);
});

it('takes part in the gcego/core package lifecycle', function (): void {
    expect(app(Gcego::class)->bootResult()?->packages)->toContain('gcego/data')
        ->and(app(Gcego::class)->hasCapability('data.eloquent'))->toBeTrue();
});

it('does not touch the database while the framework boots', function (): void {
    $this->rebuildApplication(withSchema: false);

    expect(DB::getConnections())->toBe([]);

    app(DataManager::class);
    new Source(Subject::class, new Filter('is_published', true));

    expect(DB::getConnections())->toBe([]);
});

it('reads its configuration', function (): void {
    $this->rebuildApplication(['gcego-data.pagination.max_per_page' => 30, 'gcego-data.queries.strict' => false]);

    expect(app(DataConfig::class)->maxPerPage)->toBe(30)
        ->and(app(DataConfig::class)->strict)->toBeFalse();
});

it('is the source part of blueprints', function (): void {
    $source = new Source(Subject::class);
    $blueprint = new Blueprint('subjects', $source);

    expect($blueprint->validate()->isValid())->toBeTrue()
        ->and($blueprint->part(BlueprintRole::Source))->toBe($source)
        ->and(app(DataManager::class)->sourceOf($blueprint))->toBe($source)
        ->and(app(DataManager::class)->sourceOf(new Blueprint('empty')))->toBeNull()
        ->and(fn () => new Blueprint('twice', new Source(Subject::class), new Source(Subject::class)))->not->toThrow(Throwable::class)
        ->and((new Blueprint('twice', new Source(Subject::class), new Source(Subject::class)))->validate()->isValid())->toBeFalse();
});

it('renders blueprints that contain a source without querying', function (): void {
    expect(app(RendererRegistry::class)->bindingFor(Source::class)?->rendererClass())->toBe(SourceRenderer::class);

    $queries = queriesDuring(function () use (&$html): void {
        $html = app(Runtime::class)->render(new Blueprint('subjects', new Source(Subject::class)))->toHtml();
    });

    expect($queries)->toBe([])
        ->and($html)->toContain('Subjects')
        ->and($html)->not->toContain('data.source');
});

it('registers and caches blueprints with sources without executing queries or storing callbacks', function (): void {
    $root = sys_get_temp_dir().'/gcego-data-'.bin2hex(random_bytes(4));
    mkdir($root.'/Modules/Subjects', 0777, true);
    file_put_contents($root.'/Modules/Subjects/Subjects.php', <<<'PHP'
<?php

use Gcego\Blueprints\Blueprint;
use Gcego\Data\Query\Query;
use Gcego\Data\Source;
use Gcego\Data\Tests\Fixtures\Models\Subject;

return new Blueprint('subjects', new Source(Subject::class, new Query(fn ($query) => $query->where('secret_marker_column', 1))));
PHP);

    try {
        $this->rebuildApplication([
            'gcego-registry.discovery.enabled' => true,
            'gcego-registry.discovery.roots' => [['path' => $root.'/Modules', 'kind' => 'blueprint', 'entrypoint' => 'directory']],
            'gcego-registry.cache.enabled' => true,
            'gcego-registry.cache.verify' => false,
            'gcego-registry.cache.path' => $root.'/cache/gcego-registry.php',
        ]);

        $queries = queriesDuring(function () use (&$blueprint): void {
            $this->artisan('gcego:registry-cache')->assertSuccessful();
            $blueprint = app(Registry::class)->blueprint('subjects');
        });
        $manifest = (string) file_get_contents($root.'/cache/gcego-registry.php');

        expect($queries)->toBe([])
            ->and($manifest)->toContain('subjects')
            ->and($manifest)->not->toContain('secret_marker_column')
            ->and(app(DataManager::class)->sourceOf($blueprint))->toBeInstanceOf(Source::class)
            ->and(app(DataManager::class)->explain(app(DataManager::class)->sourceOf($blueprint) ?? new Source(Subject::class))['sql'])->toContain('secret_marker_column');
    } finally {
        (new Filesystem)->deleteDirectory($root);
    }
});

it('works end to end with a source taken from a registered blueprint', function (): void {
    seedSubjects();
    app(Registry::class)->register(new Blueprint('subjects', new Source(Subject::class, new Filter('level', 1))));

    $source = app(DataManager::class)->sourceOf(app(Registry::class)->blueprint('subjects'));

    expect(pluck(app(DataManager::class)->query($source ?? new Source(Subject::class), context: QueryContext::system()), 'name'))->toBe(['Algebra']);
});
