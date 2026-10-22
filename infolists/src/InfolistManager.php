<?php

declare(strict_types=1);

namespace Gcego\Infolists;

use Closure;
use Gcego\Actions\Resolution\ActionResolver;
use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Components\Facades\Components;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Data\DataManager;
use Gcego\Data\Query\Query;
use Gcego\Data\Relationships\RelationPathValidator;
use Gcego\Data\Relationships\With;
use Gcego\Data\Security\ScopeRegistry;
use Gcego\Data\Source;
use Gcego\Infolists\Diagnostics\InfolistInspector;
use Gcego\Infolists\Diagnostics\InfolistReport;
use Gcego\Infolists\Exceptions\InvalidEntryException;
use Gcego\Infolists\Rendering\InfolistRenderContext;
use Gcego\Infolists\Rendering\InfolistRenderFrame;
use Gcego\Infolists\Resolution\EntryAccess;
use Gcego\Infolists\Resolution\ResolvedInfolist;
use Gcego\Infolists\Schema\InfolistSchema;
use Gcego\Infolists\Schema\SchemaCompiler;
use Gcego\Rendering\Context\RenderMode;
use Gcego\Rendering\Facades\Rendering;
use Gcego\Security\SecurityManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use WeakMap;

/**
 * The entry point of gcego/infolists (singleton; per-user services are
 * resolved per call):
 *
 *     $infolists = app(InfolistManager::class);
 *     echo $infolists->render('subjects', $id);            // resolve, authorize, find the record, render (no Livewire)
 *
 *     $resolved = $infolists->resolve('subjects');         // a registered blueprint only
 *     $infolists->authorize($resolved);                    // its gcego/security requirements
 *     $record = $infolists->record($resolved, $id);        // scoped lookup + view ability (404 / 403)
 *     echo $infolists->renderRecord($resolved, $record);
 *
 * Records are found through gcego/security's RecordAuthorizer on the
 * blueprint's Source (tenant scopes, data scopes, policies), with the
 * relations entries read eager-loaded under their own data scopes.
 */
final class InfolistManager
{
    /**
     * @var WeakMap<Infolist, InfolistSchema>
     */
    private WeakMap $schemas;

    /**
     * @var WeakMap<Infolist, Source>
     */
    private WeakMap $sources;

    /**
     * @var WeakMap<Infolist, array<string, Infolist>>
     */
    private WeakMap $extended;

    public function __construct(private readonly Container $container, private readonly InfolistsConfig $config)
    {
        $this->schemas = new WeakMap;
        $this->sources = new WeakMap;
        $this->extended = new WeakMap;
    }

    /**
     * The blueprint's infolist with every Components::extend() registered for
     * "{blueprint name}.infolist" applied, so modules can add, change or
     * remove entries and layouts of another module's infolist. Cached per
     * infolist instance; the patched infolist is validated like any other.
     */
    public function extended(Infolist $infolist, string $blueprint): Infolist
    {
        if (isset($this->extended[$infolist][$blueprint])) {
            return $this->extended[$infolist][$blueprint];
        }

        $patched = Components::extended($blueprint.'.infolist', $infolist);

        if (! $patched instanceof Infolist) {
            throw InvalidEntryException::invalid('Infolist', sprintf('extensions of "%s.infolist" must keep the infolist as the root.', $blueprint));
        }

        $patched->assertValid();
        $cache = $this->extended[$infolist] ?? [];
        $cache[$blueprint] = $patched;
        $this->extended[$infolist] = $cache;

        return $patched;
    }

    public function schema(Infolist $infolist): InfolistSchema
    {
        return $this->schemas[$infolist] ??= (new SchemaCompiler)->compile($infolist);
    }

    public function resolve(string $definition): ResolvedInfolist
    {
        [$key, $blueprint] = $this->container->make(ActionResolver::class)->blueprint($definition);
        $infolist = $blueprint->part(BlueprintRole::Infolist);
        $source = $blueprint->part(BlueprintRole::Source);

        if (! $infolist instanceof Infolist || ! $source instanceof Source) {
            throw new NotFoundHttpException('This definition has no infolist.');
        }

        $path = $this->pathOf($blueprint, $infolist);
        $infolist = $this->extended($infolist, $blueprint->name() ?? $key);

        return new ResolvedInfolist($key, $blueprint, $infolist, $path, $source, $this->schema($infolist));
    }

    public function authorize(ResolvedInfolist $infolist): void
    {
        $this->container->make(SecurityManager::class)->definition($infolist->blueprint, $infolist->path)->authorize();
    }

    /**
     * The record for the current user: scoped lookup (missing or out of scope: 404), then the "view" ability (403).
     */
    public function record(ResolvedInfolist $infolist, string|int $key): mixed
    {
        if (is_string($key) && ($key === '' || strlen($key) > 191)) {
            throw new NotFoundHttpException('Record not found.');
        }

        return $this->container->make(SecurityManager::class)->record($this->source($infolist), $key, 'view');
    }

    /**
     * Resolve, authorize, find and render (server-side Blade, no Livewire).
     */
    public function render(string $definition, string|int $key): HtmlString
    {
        $infolist = $this->resolve($definition);
        $this->authorize($infolist);

        return $this->renderRecord($infolist, $this->record($infolist, $key));
    }

    /**
     * Render a record the caller already resolved and authorized (for
     * example through record()). Entry access is still decided here.
     */
    public function renderRecord(ResolvedInfolist $infolist, mixed $record, ?string $hostId = null, bool $live = false): HtmlString
    {
        $frame = new InfolistRenderFrame(
            schema: $infolist->schema,
            record: $record,
            access: $this->access($infolist->schema, $record),
            config: $this->config,
            hostId: $hostId ?? 'gcego-infolist-'.substr(md5($infolist->definition), 0, 8),
            definition: $infolist->definition,
            live: $live,
        );

        $html = $this->container->make(InfolistRenderContext::class)->run($frame, fn (): string => Rendering::render($infolist->infolist, $live ? RenderMode::Livewire : RenderMode::Static, identity: $infolist->definition)->toHtml());

        return new HtmlString($html);
    }

    public function access(InfolistSchema $schema, mixed $record): EntryAccess
    {
        return EntryAccess::decide($schema, $record, $this->container->make(SecurityManager::class));
    }

    /**
     * The blueprint Source plus eager loads for the relations entries read
     * (validated against the model; each under the related model's data
     * scopes, so a related record of another tenant is never loaded) and,
     * with WithTrashed, soft-deleted records.
     */
    public function source(ResolvedInfolist $infolist): Source
    {
        return $this->sources[$infolist->infolist] ??= $this->build($infolist);
    }

    /**
     * @return array<string, class-string<Model>> valid relation path => related model
     */
    public function relations(ResolvedInfolist $infolist): array
    {
        $model = $this->model($infolist);

        if ($model === null) {
            return [];
        }

        $valid = [];

        foreach ($infolist->schema->relations as $path) {
            try {
                $valid[$path] = RelationPathValidator::assert('infolist', $model, $path);
            } catch (Throwable) {
                // Not a relation (an array or JSON attribute read by path): nothing to load.
            }
        }

        return $valid;
    }

    /**
     * @return class-string<Model>|null
     */
    public function model(ResolvedInfolist $infolist): ?string
    {
        $model = $infolist->source->get(Source::Model);

        return is_string($model) && is_subclass_of($model, Model::class) ? $model : null;
    }

    public function inspect(string|Infolist $definition): InfolistReport
    {
        return $this->container->make(InfolistInspector::class)->inspect($definition);
    }

    public function config(): InfolistsConfig
    {
        return $this->config;
    }

    private function build(ResolvedInfolist $infolist): Source
    {
        $nodes = $infolist->source->configurations()->all();
        $relations = [];

        foreach ($this->relations($infolist) as $path => $related) {
            $relations[$path] = $this->scoped($related);
        }

        if ($relations !== []) {
            $nodes[] = new With($relations);
        }

        $model = $this->model($infolist);

        if ($infolist->infolist->get(Infolist::WithTrashed) === true && $model !== null && in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            $nodes[] = new Query(static fn (Builder $query) => $query->withoutGlobalScope(SoftDeletingScope::class));
        }

        return $infolist->source->withConfigurations(...$nodes);
    }

    /**
     * An eager-load constraint applying the related model's registered data scopes (tenancy).
     *
     * @param  class-string<Model>  $related
     */
    private function scoped(string $related): Closure
    {
        $data = $this->container->make(DataManager::class);
        $scopes = $this->container->make(ScopeRegistry::class);

        return static function (mixed $query) use ($related, $data, $scopes): void {
            $builder = $query instanceof Relation ? $query->getQuery() : $query;

            if (! $builder instanceof Builder) {
                return;
            }

            $definition = $data->definition(new Source($related));

            foreach ($scopes->for($definition) as $scope) {
                $scope->apply($builder, $definition);
            }
        };
    }

    private function pathOf(BlueprintInterface $blueprint, Infolist $infolist): string
    {
        foreach (app(ComponentWalker::class)->preOrder($blueprint) as $node) {
            if ($node->component === $infolist) {
                return $node->path->toString();
            }
        }

        throw InvalidEntryException::invalid('Infolist', 'it is not part of the blueprint.');
    }
}
