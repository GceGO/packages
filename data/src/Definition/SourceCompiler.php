<?php

declare(strict_types=1);

namespace Gcego\Data\Definition;

use Gcego\Components\Validation\ValidationError;
use Gcego\Data\Contracts\SourceConfiguration;
use Gcego\Data\Exceptions\InvalidSourceException;
use Gcego\Data\Filters\Filter;
use Gcego\Data\Filters\Filterable;
use Gcego\Data\Filters\FilterOperator;
use Gcego\Data\Pagination\Paginate;
use Gcego\Data\Query\Query;
use Gcego\Data\Relationships\Includable;
use Gcego\Data\Relationships\With;
use Gcego\Data\Search\Search;
use Gcego\Data\Sorting\Sort;
use Gcego\Data\Sorting\Sortable;
use Gcego\Data\Sorting\SortDirection;
use Gcego\Data\Source;
use Gcego\Data\Support\Callback;
use Gcego\Data\Transform\Transform;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use WeakMap;

/**
 * Validates a Source and compiles its properties and configuration nodes into
 * one SourceDefinition. Pure: no database access, no container lookups.
 *
 * Property forms and node forms of the same setting are equivalent; declaring
 * both, or one setting twice, is an error rather than a silent override.
 * Results are cached per Source instance (Sources are immutable).
 */
final class SourceCompiler
{
    /**
     * @var WeakMap<Source, SourceDefinition>
     */
    private WeakMap $compiled;

    public function __construct()
    {
        $this->compiled = new WeakMap;
    }

    /**
     * @throws InvalidSourceException
     */
    public function compile(Source $source): SourceDefinition
    {
        return $this->compiled[$source] ??= $this->build($source);
    }

    private function build(Source $source): SourceDefinition
    {
        $name = $this->name($source);
        $result = $source->validate();

        if (! $result->isValid()) {
            throw InvalidSourceException::invalid($name, implode(' ', array_map(static fn (ValidationError $error): string => $error->message.($error->expected !== null ? ' Expected: '.$error->expected.'.' : ''), $result->errors())));
        }

        $constraints = [];
        $filters = [];
        $filterables = [];
        $search = null;
        $sorts = [];
        $sortables = [];
        $pagination = null;
        $with = [];
        $includables = [];
        $transforms = [];
        $extensions = [];

        $query = $source->get(Source::Query);

        if ($query instanceof Callback) {
            $constraints[] = new Query($query->closure);
        }

        foreach ($this->arrayProperty($source, Source::Filters) as $field => $value) {
            if (! is_scalar($value) && $value !== null && ! (is_array($value) && array_is_list($value) && array_filter($value, static fn (mixed $item): bool => ! is_scalar($item)) === [])) {
                throw InvalidSourceException::invalid($name, sprintf('the filters property value of "%s" must be a scalar, null or a list of scalars.', $field));
            }

            /** @var scalar|list<scalar>|null $value */
            $filters[] = is_array($value) ? new Filter((string) $field, $value, FilterOperator::In) : new Filter((string) $field, $value);
        }

        if ($source->has(Source::Search)) {
            /** @var list<string> $fields */
            $fields = $source->get(Source::Search);
            $search = new Search($fields);
        }

        foreach ($this->arrayProperty($source, Source::Sort) as $field) {
            if (is_string($field)) {
                $sorts[] = new Sort(ltrim($field, '-'), str_starts_with($field, '-') ? SortDirection::Descending : SortDirection::Ascending);
            }
        }

        if (is_int($perPage = $source->get(Source::Pagination))) {
            $pagination = new Paginate($perPage);
        }

        if ($source->has(Source::Relationships)) {
            /** @var list<string> $relations */
            $relations = $source->get(Source::Relationships);
            $with = (new With($relations))->relations;
        }

        $sortProperty = $sorts !== [];

        foreach ($source->configurations() as $node) {
            match (true) {
                $node instanceof Query => $constraints[] = $node,
                $node instanceof Filter => $filters[] = $node,
                $node instanceof Filterable => isset($filterables[$node->key])
                    ? throw InvalidSourceException::invalid($name, sprintf('filterable key "%s" is declared twice.', $node->key))
                    : $filterables[$node->key] = $node,
                $node instanceof Search => $search === null
                    ? $search = $node
                    : throw InvalidSourceException::invalid($name, 'declare searchable fields once (one Search node or the search property).'),
                $node instanceof Sort => $sortProperty
                    ? throw InvalidSourceException::invalid($name, 'declare the default ordering either with the sort property or with Sort nodes.')
                    : $sorts[] = $node,
                $node instanceof Sortable => array_intersect_key($sortables, $node->columns) !== []
                    ? throw InvalidSourceException::invalid($name, sprintf('sortable key "%s" is declared twice.', (string) array_key_first(array_intersect_key($sortables, $node->columns))))
                    : $sortables += $node->columns,
                $node instanceof Paginate => $pagination === null
                    ? $pagination = $node
                    : throw InvalidSourceException::invalid($name, 'declare pagination once (one Paginate node or the pagination property).'),
                $node instanceof With => $with = array_merge($with, $node->relations),
                $node instanceof Includable => $includables = array_merge($includables, $node->relations),
                $node instanceof Transform => $transforms[] = $node,
                $node instanceof SourceConfiguration => $extensions[] = $node,
                default => null,
            };
        }

        $builder = $source->get(Source::Builder);
        $items = $source->get(Source::Items);
        /** @var list<string>|null $columns */
        $columns = $source->get(Source::Columns);
        /** @var class-string<Model>|null $model */
        $model = $source->get(Source::Model);

        return new SourceDefinition(
            name: $name,
            provider: $this->provider($source),
            source: $source,
            model: $model,
            table: $this->stringProperty($source, Source::Table),
            connection: $this->stringProperty($source, Source::Connection),
            key: $this->stringProperty($source, Source::Key),
            columns: $columns,
            builder: $builder instanceof Callback ? $builder->closure : null,
            items: $items instanceof Callback ? $items->closure : null,
            constraints: $constraints,
            filters: $filters,
            filterables: $filterables,
            search: $search,
            sorts: $sorts,
            sortables: $sortables,
            pagination: $pagination ?? new Paginate,
            with: $with,
            includables: $includables,
            transforms: $transforms,
            extensions: $extensions,
        );
    }

    private function provider(Source $source): string
    {
        $provider = $source->get(Source::Provider);

        return match (true) {
            is_string($provider) => $provider,
            $source->has(Source::Model) => 'eloquent',
            $source->has(Source::Items) => 'collection',
            default => 'query',
        };
    }

    private function name(Source $source): string
    {
        $model = $source->get(Source::Model);
        $table = $source->get(Source::Table);

        return match (true) {
            is_string($source->name()) => $source->name(),
            is_string($model) => Str::snake(Str::pluralStudly(class_basename($model))),
            is_string($table) => $table,
            $source->has(Source::Items) => 'collection',
            default => 'query',
        };
    }

    /**
     * @return array<array-key, mixed>
     */
    private function arrayProperty(Source $source, string $key): array
    {
        $value = $source->get($key);

        return is_array($value) ? $value : [];
    }

    private function stringProperty(Source $source, string $key): ?string
    {
        $value = $source->get($key);

        return is_string($value) ? $value : null;
    }
}
