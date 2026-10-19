<?php

declare(strict_types=1);

namespace Gcego\Data\Query;

use Gcego\Data\DataConfig;
use Gcego\Data\Definition\SourceDefinition;
use Gcego\Data\Exceptions\InvalidFilterException;
use Gcego\Data\Exceptions\InvalidQueryException;
use Gcego\Data\Exceptions\InvalidSortException;
use Gcego\Data\Filters\Filter;
use Gcego\Data\Filters\Filterable;
use Gcego\Data\Filters\FilterOperator;
use Gcego\Data\Pagination\PaginationMode;
use Gcego\Data\Sorting\Sort;
use Gcego\Data\Sorting\SortDirection;

/**
 * Turns untrusted QueryInput into a QueryPlan using only the source's allowlists.
 *
 * - Filters: the key must be Filterable, the operator allowed, the value valid
 *   for its type (and options); the column comes from the definition.
 * - Search: trimmed, length- and term-limited.
 * - Sort: keys must be Sortable; defaults apply when none are requested.
 * - Pagination: positive page, page size capped by the source and the configuration.
 * - Includes: exact allowlisted relation paths.
 *
 * Unknown keys are rejected in strict mode (the default) and ignored otherwise.
 * Invalid values are always rejected.
 */
final readonly class QueryResolver
{
    public function __construct(private DataConfig $config) {}

    /**
     * @throws InvalidQueryException
     */
    public function resolve(SourceDefinition $source, QueryInput $input): QueryPlan
    {
        [$filters, $appliedFilters] = $this->filters($source, $input);
        [$sorts, $appliedSorts] = $this->sorts($source, $input);
        $includes = $this->includes($source, $input);
        $pagination = $source->pagination;
        $limit = min($pagination->max ?? $this->config->maxPerPage, $this->config->maxPerPage);
        $perPage = min($input->perPage ?? $pagination->perPage ?? $this->config->defaultPerPage, $limit);

        if ($input->cursor !== null && $pagination->mode !== PaginationMode::Cursor) {
            throw InvalidQueryException::invalid('cursor', 'is not supported by this source; use page.');
        }

        $page = $input->page ?? 1;

        if ($page > intdiv(PHP_INT_MAX, $perPage) - 1) {
            throw InvalidQueryException::invalid('page', 'is out of range.');
        }

        return new QueryPlan(
            filters: $filters,
            terms: $this->terms($source, $input),
            sorts: $sorts,
            includes: $includes,
            mode: $pagination->mode,
            perPage: $perPage,
            page: $pagination->mode === PaginationMode::Cursor ? 1 : $page,
            cursor: $input->cursor,
            applied: ['filter' => $appliedFilters, 'sort' => $appliedSorts, 'include' => array_keys($includes)],
        );
    }

    /**
     * @return array{0: list<Filter>, 1: list<string>}
     */
    private function filters(SourceDefinition $source, QueryInput $input): array
    {
        $filters = [];
        $applied = [];

        foreach ($input->filters as $key => $operators) {
            $filterable = $source->filterables[$key] ?? null;

            if ($filterable === null) {
                if ($this->config->strict) {
                    throw InvalidFilterException::unknownField($key, array_keys($source->filterables));
                }

                continue;
            }

            foreach ($operators as $name => $value) {
                $operator = FilterOperator::tryFrom($name);

                if ($operator === null || ! $filterable->allows($operator)) {
                    throw InvalidFilterException::unsupportedOperator($key, $name, array_map(static fn (FilterOperator $allowed): string => $allowed->value, $filterable->operators));
                }

                // An empty value (?filter[status]=) means "no filter", as forms submit it for "All".
                if ($value === '' || $value === null || $value === []) {
                    if ($operator->arity() !== 0) {
                        continue;
                    }
                }

                $filters[] = new Filter($filterable->column, $this->value($filterable, $operator, $value), $operator);
                $applied[] = $key;
            }
        }

        return [$filters, array_values(array_unique($applied))];
    }

    /**
     * @return string|int|float|bool|list<string|int|float|bool>|null
     */
    private function value(Filterable $filterable, FilterOperator $operator, mixed $value): mixed
    {
        $key = $filterable->key;

        if ($operator->arity() === 0) {
            return in_array($value, [null, '', true, 1, '1', 'true'], true)
                ? null
                : throw InvalidFilterException::invalidValue($key, sprintf('%s takes no value (use 1 or true).', $operator->value));
        }

        if (! is_array($value) && ! is_scalar($value)) {
            throw InvalidFilterException::invalidValue($key, 'must be a value or a list of values.');
        }

        $values = is_array($value) ? $value : ($operator->arity() === 1 ? [$value] : explode(',', (string) $value));

        if ($operator->arity() === 1 && count($values) !== 1) {
            throw InvalidFilterException::invalidValue($key, sprintf('%s takes a single value.', $operator->value));
        }

        if ($operator === FilterOperator::Between && count($values) !== 2) {
            throw InvalidFilterException::invalidValue($key, 'between takes exactly two values.');
        }

        if ($values === [] || count($values) > $this->config->filterMaxValues) {
            throw InvalidFilterException::invalidValue($key, sprintf('takes between 1 and %d values.', $this->config->filterMaxValues));
        }

        $cast = [];

        foreach ($values as $item) {
            $item = $filterable->type->cast($key, $item);

            if ($filterable->options !== [] && ! in_array($item, $filterable->options, true)) {
                throw InvalidFilterException::invalidValue($key, 'is not one of the allowed options.');
            }

            $cast[] = $item;
        }

        return $operator->arity() === 1 ? $cast[0] : $cast;
    }

    /**
     * @return list<string>
     */
    private function terms(SourceDefinition $source, QueryInput $input): array
    {
        if ($input->search === null) {
            return [];
        }

        if ($source->search === null) {
            if ($this->config->strict) {
                throw InvalidQueryException::invalid('search', 'is not supported by this source.');
            }

            return [];
        }

        if (mb_strlen($input->search) > $this->config->searchMaxLength) {
            throw InvalidQueryException::tooLong('search', $this->config->searchMaxLength);
        }

        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $input->search) === 1) {
            throw InvalidQueryException::invalid('search', 'contains control characters.');
        }

        $terms = array_values(array_unique(preg_split('/\s+/u', $input->search, -1, PREG_SPLIT_NO_EMPTY) ?: []));

        if (count($terms) > $this->config->searchMaxTerms) {
            throw InvalidQueryException::invalid('search', sprintf('has more than %d words.', $this->config->searchMaxTerms));
        }

        return $terms;
    }

    /**
     * @return array{0: list<array{column: string, direction: SortDirection}>, 1: list<string>}
     */
    private function sorts(SourceDefinition $source, QueryInput $input): array
    {
        if (count($input->sorts) > $this->config->sortMaxFields) {
            throw InvalidSortException::tooMany($this->config->sortMaxFields);
        }

        $sorts = [];
        $applied = [];

        foreach ($input->sorts as ['key' => $key, 'direction' => $direction]) {
            $column = $source->sortables[$key] ?? null;

            if ($column === null) {
                if ($this->config->strict) {
                    throw InvalidSortException::unknownField($key, array_keys($source->sortables));
                }

                continue;
            }

            $sorts[$column] ??= ['column' => $column, 'direction' => $direction];
            $applied[] = $key;
        }

        if ($sorts === []) {
            foreach ($source->sorts as $sort) {
                /** @var Sort $sort */
                $sorts[$sort->field] ??= ['column' => $sort->field, 'direction' => $sort->direction];
            }
        }

        return [array_values($sorts), $applied];
    }

    /**
     * @return array<string, \Closure|null>
     */
    private function includes(SourceDefinition $source, QueryInput $input): array
    {
        $includes = [];

        foreach ($input->includes as $include) {
            if (! array_key_exists($include, $source->includables)) {
                if ($this->config->strict) {
                    throw InvalidQueryException::unknownInclude($include, array_keys($source->includables));
                }

                continue;
            }

            $includes[$include] = $source->includables[$include];
        }

        return $includes;
    }
}
