<?php

declare(strict_types=1);

namespace Gcego\Tables\Query;

use Gcego\Data\DataManager;
use Gcego\Data\Filters\Filterable;
use Gcego\Data\Pagination\Paginate;
use Gcego\Data\Pagination\PaginationMode;
use Gcego\Data\Relationships\With;
use Gcego\Data\Search\Search;
use Gcego\Data\Sorting\Sortable;
use Gcego\Data\Source;
use Gcego\Tables\Schema\TableSchema;
use Gcego\Tables\TablesConfig;
use WeakMap;

/**
 * The blueprint's Source plus the allowlists the table declares, for one
 * set of readable columns:
 *
 * - Search over the readable Searchable columns (unless the Source declares its own Search);
 * - Sortable keys for readable Sortable columns (the Source's own keys win);
 * - a Filterable per filter (skipped when the Source declares that key);
 * - With for every relation readable columns read (no lazy loading per cell);
 * - Paginate from the table's Pagination when the Source sets none;
 * - the table's default Sort nodes when the Source sets none.
 *
 * Unreadable columns never become searchable or sortable, so they cannot be
 * probed through search or ordering. Mandatory Source constraints, scopes
 * and authorization are untouched: the result is still executed by gcego/data.
 *
 * Cached per Source and access set (definitions are immutable).
 */
final class EffectiveSources
{
    /**
     * @var WeakMap<Source, array<string, Source>>
     */
    private WeakMap $cache;

    public function __construct(private readonly DataManager $data, private readonly TablesConfig $config)
    {
        $this->cache = new WeakMap;
    }

    public function for(Source $source, TableSchema $schema, ColumnAccess $access, bool $export = false): Source
    {
        $key = spl_object_id($schema->table).'|'.$access->fingerprint().($export ? '|export' : '');

        $variants = $this->cache[$source] ?? [];

        if (! isset($variants[$key])) {
            $variants[$key] = $this->build($source, $schema, $access, $export);
            $this->cache[$source] = $variants;
        }

        return $variants[$key];
    }

    private function build(Source $source, TableSchema $schema, ColumnAccess $access, bool $export): Source
    {
        $base = $this->data->definition($source);
        $nodes = $source->configurations()->all();
        $explicitPagination = $source->has(Source::Pagination) || $source->configurations()->has(Paginate::class);

        if ($base->search === null) {
            $fields = [];

            foreach ($schema->columns as $name => $column) {
                if ($column->isSearchable() && $access->canRead($name)) {
                    $fields = [...$fields, ...$column->searchFields()];
                }
            }

            if ($fields !== []) {
                $nodes[] = new Search(...array_values(array_unique($fields)));
            }
        }

        $sortable = [];

        foreach ($schema->sortable() as $key => $column) {
            if ($access->canRead($column->columnName()) && ! isset($base->sortables[$key])) {
                $sortable[$key] = $column->sortColumn();
            }
        }

        if ($sortable !== []) {
            $nodes[] = Sortable::map($sortable);
        }

        foreach ($schema->filters as $name => $filter) {
            $filterable = $filter->filterable();

            if ($filterable instanceof Filterable && ! isset($base->filterables[$filterable->key])) {
                $nodes[] = $filterable;
            }
        }

        $relations = [];

        foreach ($schema->columns as $name => $column) {
            if ($access->canRead($name) || $access->isMasked($name)) {
                foreach ($column->relations() as $relation) {
                    if (! isset($base->with[$relation])) {
                        $relations[] = $relation;
                    }
                }
            }
        }

        if ($relations !== []) {
            $nodes[] = new With(...array_values(array_unique($relations)));
        }

        if ($export) {
            $nodes = array_values(array_filter($nodes, static fn (mixed $node): bool => ! $node instanceof Paginate));
            $nodes[] = Paginate::simple($this->config->exportChunk, max: $this->config->exportChunk);
            $source = $source->withoutProperty(Source::Pagination);
        } elseif (! $explicitPagination) {
            $pagination = $schema->pagination;
            $nodes[] = new Paginate(min($pagination->perPage ?? $this->config->defaultPerPage, $this->config->maxPerPage), $pagination->mode ?? PaginationMode::Pages, $this->config->maxPerPage);
        }

        if ($base->sorts === [] && $schema->defaultSorts !== []) {
            $nodes = [...$nodes, ...$schema->defaultSorts];
        }

        return $source->withConfigurations(...$nodes);
    }
}
