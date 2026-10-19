<?php

declare(strict_types=1);

namespace Gcego\Data\Definition;

use Closure;
use Gcego\Data\Contracts\QueryConstraint;
use Gcego\Data\Contracts\RecordTransformer;
use Gcego\Data\Contracts\SourceConfiguration;
use Gcego\Data\Filters\Filter;
use Gcego\Data\Filters\Filterable;
use Gcego\Data\Pagination\Paginate;
use Gcego\Data\Search\Search;
use Gcego\Data\Sorting\Sort;
use Gcego\Data\Source;
use Illuminate\Database\Eloquent\Model;

/**
 * The compiled, immutable form of a Source: every property and configuration
 * node merged into one typed definition. Built once per Source by SourceCompiler.
 */
final readonly class SourceDefinition
{
    /**
     * @param  class-string<Model>|null  $model
     * @param  list<string>|null  $columns
     * @param  list<QueryConstraint>  $constraints  Trusted Query nodes, in order.
     * @param  list<Filter>  $filters  Fixed conditions.
     * @param  array<string, Filterable>  $filterables  Request filter allowlist by key.
     * @param  list<Sort>  $sorts  Default ordering.
     * @param  array<string, string>  $sortables  Request sort allowlist: key => column.
     * @param  array<string, Closure|null>  $with  Always eager loaded.
     * @param  array<string, Closure|null>  $includables  Request include allowlist.
     * @param  list<RecordTransformer>  $transforms
     * @param  list<SourceConfiguration>  $extensions  Configuration nodes from other packages.
     */
    public function __construct(
        public string $name,
        public string $provider,
        public Source $source,
        public ?string $model = null,
        public ?string $table = null,
        public ?string $connection = null,
        public ?string $key = null,
        public ?array $columns = null,
        public ?Closure $builder = null,
        public ?Closure $items = null,
        public array $constraints = [],
        public array $filters = [],
        public array $filterables = [],
        public ?Search $search = null,
        public array $sorts = [],
        public array $sortables = [],
        public Paginate $pagination = new Paginate,
        public array $with = [],
        public array $includables = [],
        public array $transforms = [],
        public array $extensions = [],
    ) {}

    /**
     * Configuration nodes of another package, by class.
     *
     * @template T of SourceConfiguration
     *
     * @param  class-string<T>  $class
     * @return list<T>
     */
    public function extensions(string $class): array
    {
        return array_values(array_filter($this->extensions, static fn (SourceConfiguration $node): bool => $node instanceof $class));
    }

    /**
     * Safe, scalar description for diagnostics and tooling. Closures are counted, never described.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'provider' => $this->provider,
            'model' => $this->model,
            'table' => $this->table,
            'connection' => $this->connection,
            'key' => $this->key,
            'columns' => $this->columns,
            'constraints' => count($this->constraints) + ($this->builder !== null ? 1 : 0),
            'filters' => array_map(static fn (Filter $filter): array => ['field' => $filter->field, 'operator' => $filter->operator->value], $this->filters),
            'filterable' => array_map(static fn (Filterable $filterable): array => $filterable->normalize(), $this->filterables),
            'search' => $this->search?->normalize(),
            'sort' => array_map(static fn (Sort $sort): array => $sort->normalize(), $this->sorts),
            'sortable' => $this->sortables,
            'pagination' => $this->pagination->normalize(),
            'with' => array_keys($this->with),
            'includable' => array_keys($this->includables),
            'transforms' => count($this->transforms),
            'extensions' => array_map(static fn (SourceConfiguration $node): string => $node::class, $this->extensions),
        ];
    }
}
