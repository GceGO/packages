<?php

declare(strict_types=1);

namespace Gcego\Tables\Diagnostics;

use Gcego\Actions\ActionPlacement;
use Gcego\Actions\Resolution\ActionResolver;
use Gcego\Chroma\Slots\SlotRegistry;
use Gcego\Data\DataManager;
use Gcego\Data\Pagination\PaginationMode;
use Gcego\Data\Relationships\RelationPathValidator;
use Gcego\Data\Support\Callback;
use Gcego\Runtime\Rendering\RendererRegistry;
use Gcego\Security\Contracts\Requirement;
use Gcego\Tables\Filters\SelectFilter;
use Gcego\Tables\Query\ColumnAccess;
use Gcego\Tables\Query\EffectiveSources;
use Gcego\Tables\Schema\SchemaCompiler;
use Gcego\Tables\Schema\TableSchema;
use Gcego\Tables\Table;
use Gcego\Tables\TableManager;
use Gcego\Tables\TablesConfig;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Describes a table without running its queries: columns (searchable,
 * sortable, hideable, access), filters, actions by placement, and findings:
 *
 * schema (duplicates, invalid definitions), source (missing, unhealthy),
 * relations (unknown relation paths), hidden (columns reading $hidden
 * attributes), effective (the combined Source does not compile), renderer,
 * styling, authorization (no requirement: everyone is denied), storage
 * (preference and view tables missing), performance (relation search,
 * COUNT pagination, option callbacks). Never reports record values.
 */
final readonly class TableInspector
{
    public function __construct(private Container $container, private TablesConfig $config) {}

    public function inspect(string|Table $definition): TableReport
    {
        $diagnostics = [];
        $resolved = null;

        try {
            if (is_string($definition)) {
                $resolved = $this->container->make(TableManager::class)->resolve($definition);
                $table = $resolved->table;
            } else {
                $table = $definition;
            }

            $schema = (new SchemaCompiler)->compile($table);
        } catch (Throwable $exception) {
            return new TableReport(is_string($definition) ? $definition : $definition->tableName(), is_string($definition) ? $definition : null, [], [], [], [new TableDiagnostic('schema', TableDiagnostic::Error, $exception->getMessage())]);
        }

        foreach ($table->validate()->errors() as $error) {
            $diagnostics[] = new TableDiagnostic('schema', TableDiagnostic::Error, (string) $error);
        }

        $model = null;
        $actions = [];

        if ($resolved !== null) {
            $data = $this->container->make(DataManager::class);
            $report = $data->inspect($resolved->source);

            if (! $report->isHealthy()) {
                $diagnostics[] = new TableDiagnostic('source', TableDiagnostic::Error, 'The blueprint Source has problems; see DataManager::inspect() for details.');
            }

            try {
                $model = $data->definition($resolved->source)->model;
                $data->definition($this->container->make(EffectiveSources::class)->for($resolved->source, $schema, ColumnAccess::all($schema)));
            } catch (Throwable $exception) {
                $diagnostics[] = new TableDiagnostic('effective', TableDiagnostic::Error, $exception->getMessage());
            }

            foreach ([ActionPlacement::Header, ActionPlacement::Toolbar, ActionPlacement::Row, ActionPlacement::Bulk] as $placement) {
                try {
                    $actions[$placement] = array_map(static fn ($action): string => $action->name(), $this->container->make(ActionResolver::class)->actions($resolved->definition, $placement));
                } catch (Throwable $exception) {
                    $diagnostics[] = new TableDiagnostic('actions', TableDiagnostic::Error, $exception->getMessage());
                }
            }

            $requirements = array_filter([...$resolved->blueprint->configurations()->all(), ...$table->configurations()->all()], static fn (mixed $node): bool => $node instanceof Requirement);

            if ($requirements === []) {
                $diagnostics[] = new TableDiagnostic('authorization', TableDiagnostic::Error, 'The blueprint declares no gcego/security requirement, so every user is denied; add an Ability, Permission or ResourceAccess node.');
            }
        }

        $diagnostics = [...$diagnostics, ...$this->columns($schema, $model), ...$this->filters($schema), ...$this->platform($schema)];

        if ($diagnostics === []) {
            $diagnostics[] = new TableDiagnostic('table', TableDiagnostic::Ok, sprintf('Table "%s" has %d column(s) and no problems.', $table->tableName(), count($schema->columns)));
        }

        return new TableReport(
            $table->tableName(),
            $resolved?->definition,
            array_values(array_map(static fn ($column): array => [
                'name' => $column->columnName(),
                'type' => $column::class,
                'searchable' => $column->isSearchable(),
                'sortable' => $column->isSortable(),
                'hideable' => $column->isHideable(),
                'visible' => $column->isVisibleByDefault(),
                'access' => $column->access()?->read?->describe(),
                'relations' => $column->relations(),
            ], $schema->columns)),
            array_values(array_map(static fn ($filter): array => ['name' => $filter->filterName(), 'type' => $filter::class, 'field' => $filter->field()], $schema->filters)),
            $actions,
            $diagnostics,
        );
    }

    /**
     * @param  class-string<Model>|null  $model
     * @return list<TableDiagnostic>
     */
    private function columns(TableSchema $schema, ?string $model): array
    {
        $diagnostics = [];
        $hidden = $model === null ? [] : (new $model)->getHidden();

        foreach ($schema->columns as $name => $column) {
            $details = ['column' => $name];

            if ($model !== null) {
                foreach ($column->relations() as $relation) {
                    try {
                        RelationPathValidator::assert('table', $model, $relation);
                    } catch (Throwable $exception) {
                        $diagnostics[] = new TableDiagnostic('relations', TableDiagnostic::Error, $exception->getMessage(), $details);
                    }
                }
            }

            if (! $column->isRelationPath() && in_array($name, $hidden, true)) {
                $diagnostics[] = new TableDiagnostic('hidden', TableDiagnostic::Error, sprintf('Column "%s" reads an attribute in the model\'s $hidden list; it always renders empty.', $name), $details);
            }

            if ($column->isSearchable() && array_filter($column->searchFields(), static fn (string $field): bool => str_contains($field, '.')) !== []) {
                $diagnostics[] = new TableDiagnostic('performance', TableDiagnostic::Warning, sprintf('Column "%s" searches through a relation (whereHas with LIKE); index the related column or use a full-text SearchStrategy on large tables.', $name), $details);
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<TableDiagnostic>
     */
    private function filters(TableSchema $schema): array
    {
        $diagnostics = [];

        foreach ($schema->filters as $name => $filter) {
            if ($filter instanceof SelectFilter && $filter->get(SelectFilter::Options) instanceof Callback) {
                $diagnostics[] = new TableDiagnostic('performance', TableDiagnostic::Warning, sprintf('Filter "%s" computes its options with a callback on every render and validation; keep it cheap or use static options.', $name), ['filter' => $name]);
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<TableDiagnostic>
     */
    private function platform(TableSchema $schema): array
    {
        $diagnostics = [];
        $renderers = $this->container->bound(RendererRegistry::class) ? $this->container->make(RendererRegistry::class) : null;
        $slots = $this->container->bound(SlotRegistry::class) ? $this->container->make(SlotRegistry::class) : null;

        foreach ([$schema->table, ...array_values($schema->columns), ...array_values($schema->filters)] as $component) {
            if ($renderers !== null && ! $renderers->has($component)) {
                $diagnostics[] = new TableDiagnostic('renderer', TableDiagnostic::Error, sprintf('%s has no renderer.', $component::class));
            }

            if ($slots !== null && $slots->for($component) === null) {
                $diagnostics[] = new TableDiagnostic('styling', TableDiagnostic::Warning, sprintf('%s has no styling slots.', $component::class));
            }
        }

        if (($schema->pagination->mode ?? PaginationMode::Pages) === PaginationMode::Pages) {
            $diagnostics[] = new TableDiagnostic('performance', TableDiagnostic::Ok, 'Page-numbered pagination runs one COUNT query per page; use PaginationMode::Simple or Cursor for very large tables.');
        }

        foreach (['preferences' => [$this->config->preferences, $this->config->preferencesTable], 'saved_views' => [$this->config->savedViews, $this->config->viewsTable]] as $feature => [$enabled, $tableName]) {
            if (! $enabled) {
                continue;
            }

            try {
                if (! Schema::connection($this->config->connection)->hasTable($tableName)) {
                    $diagnostics[] = new TableDiagnostic('storage', TableDiagnostic::Warning, sprintf('gcego-tables.%s is enabled but the "%s" table does not exist; publish and run the gcego-tables migrations.', $feature, $tableName));
                }
            } catch (Throwable) {
                $diagnostics[] = new TableDiagnostic('storage', TableDiagnostic::Warning, sprintf('The %s storage could not be checked.', $feature));
            }
        }

        return $diagnostics;
    }
}
