<?php

declare(strict_types=1);

namespace Gcego\Tables;

use Gcego\Actions\Resolution\ActionResolver;
use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Contracts\BlueprintInterface;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Data\DataManager;
use Gcego\Data\Query\QueryContext;
use Gcego\Data\Query\QueryInput;
use Gcego\Data\Results\DataResult;
use Gcego\Data\Source;
use Gcego\Security\SecurityManager;
use Gcego\Tables\Diagnostics\TableInspector;
use Gcego\Tables\Diagnostics\TableReport;
use Gcego\Tables\Exceptions\InvalidTableException;
use Gcego\Tables\Export\TableExport;
use Gcego\Tables\Query\ColumnAccess;
use Gcego\Tables\Query\EffectiveSources;
use Gcego\Tables\Query\ResolvedTable;
use Gcego\Tables\Schema\SchemaCompiler;
use Gcego\Tables\Schema\TableSchema;
use Gcego\Tables\State\TableState;
use Gcego\Tables\State\TableStateNormalizer;
use Gcego\Tables\Support\TableOwner;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WeakMap;

/**
 * The entry point of gcego/tables (singleton; per-user services are
 * resolved per call, so it is safe in long-running workers):
 *
 *     $tables = app(TableManager::class);
 *     $resolved = $tables->resolve('subjects');                 // a registered blueprint only
 *     $tables->authorize($resolved);                            // gcego/security definition requirements
 *     $access = $tables->access($resolved->schema);             // column read rules for this user
 *     $state = $tables->normalizer($resolved->schema, $access)->normalize($untrusted);
 *     $result = $tables->query($resolved, $access, $state);     // gcego/data, scoped and authorized
 *
 * Tables never execute SQL themselves: every query goes through gcego/data.
 */
final class TableManager
{
    /**
     * @var WeakMap<Table, TableSchema>
     */
    private WeakMap $schemas;

    public function __construct(private readonly Container $container, private readonly TablesConfig $config)
    {
        $this->schemas = new WeakMap;
    }

    public function schema(Table $table): TableSchema
    {
        return $this->schemas[$table] ??= (new SchemaCompiler)->compile($table);
    }

    /**
     * The table of a registered blueprint. Unknown identities and blueprints
     * without a table or source are refused (404), so a client can never
     * point a table at an arbitrary model or class.
     */
    public function resolve(string $definition): ResolvedTable
    {
        [$key, $blueprint] = $this->container->make(ActionResolver::class)->blueprint($definition);
        $table = $blueprint->part(BlueprintRole::Table);
        $source = $blueprint->part(BlueprintRole::Source);

        if (! $table instanceof Table || ! $source instanceof Source) {
            throw new NotFoundHttpException('This definition has no table.');
        }

        return new ResolvedTable($key, $blueprint, $table, $this->pathOf($blueprint, $table), $source, $this->schema($table));
    }

    /**
     * gcego/security requirements of the blueprint and the table (403 when denied).
     */
    public function authorize(ResolvedTable $table): void
    {
        $this->container->make(SecurityManager::class)->definition($table->blueprint, $table->path)->authorize();
    }

    public function access(TableSchema $schema): ColumnAccess
    {
        return ColumnAccess::decide($schema, $this->container->make(SecurityManager::class));
    }

    /**
     * @param  class-string<Model>|null  $model  The source model, for relationship filter options.
     */
    public function normalizer(TableSchema $schema, ColumnAccess $access, bool $strict = true, ?string $model = null): TableStateNormalizer
    {
        return new TableStateNormalizer($schema, $this->config, $access->readable, $strict, $access->masked, $model);
    }

    /**
     * The Eloquent model of a resolved table's source, or null for other providers.
     *
     * @return class-string<Model>|null
     */
    public function model(ResolvedTable $table): ?string
    {
        $model = $table->source->get(Source::Model);

        return is_string($model) && is_subclass_of($model, Model::class) ? $model : null;
    }

    /**
     * One page of rows for this user and state, through gcego/data (policies,
     * tenant scopes, mandatory constraints and allowlists all apply).
     */
    public function query(ResolvedTable $table, ColumnAccess $access, TableState $state): DataResult
    {
        $source = $this->container->make(EffectiveSources::class)->for($table->source, $table->schema, $access);

        return $this->container->make(DataManager::class)->query($source, $this->input($table->schema, $state), $this->context($table->schema, $state));
    }

    /**
     * The gcego/data request for a state (filters by their own parameters, search, sorting, pagination).
     */
    public function input(TableSchema $schema, TableState $state, bool $paginate = true): QueryInput
    {
        $filters = [];

        foreach ($state->filters as $name => $value) {
            $filter = $schema->filter($name);

            if ($filter !== null) {
                $filters = [...$filters, ...$filter->queryInput($value)];
            }
        }

        return QueryInput::fromArray(array_filter([
            'filter' => $filters,
            'search' => $state->search,
            'sort' => implode(',', array_map(static fn (array $sort): string => ($sort['direction'] === 'desc' ? '-' : '').$sort['column'], $state->sorts)),
            'page' => $paginate && $state->cursor === null ? $state->page : null,
            'per_page' => $paginate ? $state->perPage : null,
            'cursor' => $paginate ? $state->cursor : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []));
    }

    /**
     * The current user plus the trusted constraints of active custom filters.
     */
    public function context(TableSchema $schema, TableState $state): QueryContext
    {
        $context = QueryContext::for($this->container->make(SecurityManager::class)->context()->user);

        foreach ($state->filters as $name => $value) {
            $constraint = $schema->filter($name)?->constraint($value);

            if ($constraint !== null) {
                $context = $context->constrain($constraint);
            }
        }

        return $context;
    }

    /**
     * An export of the current user's view of the table (authorized now,
     * columns limited to readable, exportable ones), streamed in chunks.
     *
     * @param  array<array-key, mixed>  $state  Untrusted table state (search, filters, sorts, order, hidden).
     */
    public function export(string $definition, array $state = [], bool $visibleOnly = true): TableExport
    {
        $table = $this->resolve($definition);
        $this->authorize($table);
        $access = $this->access($table->schema);
        $normalized = $this->normalizer($table->schema, $access, model: $this->model($table))->normalize($state);
        $source = $this->container->make(EffectiveSources::class)->for($table->source, $table->schema, $access, export: true);
        $columns = array_values(array_filter(
            $visibleOnly ? $normalized->visibleColumns() : $normalized->order,
            static fn (string $name): bool => $access->canRead($name) && ($table->schema->column($name)?->isExportable() ?? false),
        ));

        return new TableExport($this->container->make(DataManager::class), $source, $table->schema, $this->input($table->schema, $normalized, paginate: false), $this->context($table->schema, $normalized), $columns, $this->config);
    }

    public function owner(): ?TableOwner
    {
        return TableOwner::from($this->container->make(SecurityManager::class)->context());
    }

    public function inspect(string|Table $definition): TableReport
    {
        return $this->container->make(TableInspector::class)->inspect($definition);
    }

    public function config(): TablesConfig
    {
        return $this->config;
    }

    private function pathOf(BlueprintInterface $blueprint, Table $table): string
    {
        foreach ((new ComponentWalker)->preOrder($blueprint) as $node) {
            if ($node->component === $table) {
                return $node->path->toString();
            }
        }

        throw InvalidTableException::invalid('Table', 'it is not part of the blueprint.');
    }
}
