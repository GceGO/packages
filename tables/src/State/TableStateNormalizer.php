<?php

declare(strict_types=1);

namespace Gcego\Tables\State;

use Gcego\Tables\Exceptions\InvalidTableStateException;
use Gcego\Tables\Schema\TableSchema;
use Gcego\Tables\Table;
use Gcego\Tables\TablesConfig;
use Illuminate\Database\Eloquent\Model;

/**
 * Turns untrusted state (Livewire properties, the URL, stored preferences,
 * saved views) into TableState, using only the definition:
 *
 * - search: a trimmed string, at most search.max_length characters; only
 *   when the table has searchable (readable) columns;
 * - filters: declared filter names only, each value validated by its filter;
 * - sorts: sortable, readable columns only; one unless MultiSort;
 * - page and per_page: positive, per_page one of the offered sizes;
 * - order and hidden: readable column names only; unhideable columns cannot be hidden.
 *
 * Strict mode (requests) throws InvalidTableStateException for anything
 * unknown; lenient mode (stored state, which may predate a definition
 * change) drops what no longer applies and reports it in $dropped.
 */
final class TableStateNormalizer
{
    /**
     * @var list<string>
     */
    public array $dropped = [];

    public function __construct(
        private readonly TableSchema $schema,
        private readonly TablesConfig $config,
        /** @var list<string> Columns the user may read (Access). */
        private readonly array $readable,
        private readonly bool $strict = true,
        /** @var list<string> Columns shown masked: displayable, never searched, sorted or filtered. */
        private readonly array $masked = [],
        /** @var class-string<Model>|null */
        private readonly ?string $model = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $input  Keys: search, filters, sorts, page, per_page, cursor, order, hidden, density.
     */
    public function normalize(array $input): TableState
    {
        $this->dropped = [];
        [$order, $hidden] = $this->columns($input['order'] ?? null, $input['hidden'] ?? null);

        return new TableState(
            search: $this->search($input['search'] ?? null),
            filters: $this->filters($input['filters'] ?? []),
            sorts: $this->sorts($input['sorts'] ?? []),
            page: $this->page($input['page'] ?? 1),
            perPage: $this->perPage($input['per_page'] ?? null),
            cursor: $this->cursor($input['cursor'] ?? null),
            order: $order,
            hidden: $hidden,
            density: $this->density($input['density'] ?? null),
        );
    }

    /**
     * The defaults of the definition for this user.
     */
    public function defaults(): TableState
    {
        $filters = [];

        foreach ($this->schema->filters as $name => $filter) {
            if ($this->filterAllowed($name)) {
                $state = $filter->defaultState();

                if ($state !== null) {
                    $filters[$name] = $state;
                }
            }
        }

        [$order, $hidden] = $this->columns(null, null);

        return new TableState(filters: $filters, perPage: $this->perPage(null), order: $order, hidden: $hidden, density: $this->schema->table->density());
    }

    /**
     * @return list<int>
     */
    public function perPageOptions(): array
    {
        $options = $this->schema->pagination?->options ?: $this->config->perPageOptions;
        $default = $this->schema->pagination->perPage ?? $this->config->defaultPerPage;
        $options = array_values(array_unique([...array_filter($options, fn (int $size): bool => $size <= $this->config->maxPerPage), min($default, $this->config->maxPerPage)]));
        sort($options);

        return $options;
    }

    private function search(mixed $search): ?string
    {
        if ($search === null || $search === '') {
            return null;
        }

        if (! is_string($search) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $search) === 1) {
            return $this->fail('search', 'must be text.', null);
        }

        $search = trim((string) preg_replace('/\s+/u', ' ', $search));

        if ($search === '') {
            return null;
        }

        if (! $this->searchable()) {
            return $this->fail('search', 'is not available for this table.', null);
        }

        return mb_substr($search, 0, $this->config->searchMaxLength);
    }

    public function searchable(): bool
    {
        foreach ($this->schema->columns as $name => $column) {
            if ($column->isSearchable() && in_array($name, $this->readable, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(mixed $filters): array
    {
        if ($filters === null || $filters === []) {
            return [];
        }

        if (! is_array($filters)) {
            return $this->fail('filters', 'must be a group of filters.', []);
        }

        $normalized = [];

        foreach ($filters as $name => $value) {
            $name = (string) $name;
            $filter = $this->schema->filter($name);

            if ($filter === null || ! $this->filterAllowed($name)) {
                $this->fail('filters.'.$name, 'is not a filter of this table.', null);

                continue;
            }

            try {
                $state = $filter->normalizeState($value, $this->model);
            } catch (InvalidTableStateException $exception) {
                if ($this->strict) {
                    throw $exception;
                }

                $this->dropped[] = 'filters.'.$name;

                continue;
            }

            if ($state !== null) {
                $normalized[$name] = $state;
            }
        }

        return $normalized;
    }

    /**
     * A filter on an unreadable column's field would reveal its values.
     */
    public function filterAllowed(string $name): bool
    {
        $filter = $this->schema->filter($name);

        if ($filter === null) {
            return false;
        }

        $field = $filter->field();
        $column = $this->schema->column($field) ?? $this->schema->column($name);

        return $column === null || in_array($column->columnName(), $this->readable, true);
    }

    /**
     * @return list<array{column: string, direction: 'asc'|'desc'}>
     */
    private function sorts(mixed $sorts): array
    {
        if ($sorts === null || $sorts === []) {
            return [];
        }

        if (! is_array($sorts) || ! array_is_list($sorts)) {
            return $this->fail('sorts', 'must be a list.', []);
        }

        $sortable = $this->schema->sortable();
        $normalized = [];

        foreach ($sorts as $sort) {
            $column = is_array($sort) ? ($sort['column'] ?? null) : null;
            $direction = is_array($sort) ? ($sort['direction'] ?? 'asc') : null;

            if (! is_string($column) || ! isset($sortable[$column]) || ! in_array($column, $this->readable, true) || ! in_array($direction, ['asc', 'desc'], true)) {
                $this->fail('sorts', 'names a column that cannot be sorted.', null);

                continue;
            }

            /** @var 'asc'|'desc' $direction */
            $normalized[$column] = ['column' => $column, 'direction' => $direction];
        }

        $normalized = array_values($normalized);
        $max = $this->schema->multiSort() ? 3 : 1;

        if (count($normalized) > $max) {
            $normalized = array_slice($normalized, 0, $max);
        }

        return $normalized;
    }

    private function page(mixed $page): int
    {
        if (is_int($page) && $page >= 1 && $page <= 1_000_000) {
            return $page;
        }

        if (is_string($page) && preg_match('/^\d{1,7}$/', $page) === 1 && (int) $page >= 1) {
            return (int) $page;
        }

        return $this->fail('page', 'must be a positive page number.', 1);
    }

    private function perPage(mixed $perPage): int
    {
        $options = $this->perPageOptions();
        $default = min($this->schema->pagination->perPage ?? $this->config->defaultPerPage, $this->config->maxPerPage);

        if ($perPage === null || $perPage === '') {
            return $default;
        }

        $value = is_int($perPage) ? $perPage : (is_string($perPage) && preg_match('/^\d{1,4}$/', $perPage) === 1 ? (int) $perPage : null);

        return $value !== null && in_array($value, $options, true) ? $value : $this->fail('per_page', 'must be one of the offered page sizes.', $default);
    }

    private function cursor(mixed $cursor): ?string
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        return is_string($cursor) && strlen($cursor) <= 2048 && preg_match('/^[A-Za-z0-9_\-=]+$/', $cursor) === 1 ? $cursor : $this->fail('cursor', 'is not a valid cursor.', null);
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    private function columns(mixed $order, mixed $hidden): array
    {
        $readable = array_values(array_filter($this->schema->columnNames(), fn (string $name): bool => in_array($name, $this->readable, true) || in_array($name, $this->masked, true)));
        $defaultHidden = array_values(array_filter($readable, fn (string $name): bool => ! $this->schema->columns[$name]->isVisibleByDefault()));

        $ordered = [];
        $known = [];

        if ($order !== null) {
            if (! is_array($order) || ! array_is_list($order)) {
                $this->fail('order', 'must be a list of columns.', null);
            } else {
                foreach ($order as $name) {
                    if (is_string($name) && in_array($name, $readable, true)) {
                        $ordered[$name] = $name;
                        $known[$name] = true;
                    } else {
                        $this->fail('order', 'names an unknown column.', null);
                    }
                }
            }
        }

        foreach ($readable as $name) {
            $ordered[$name] ??= $name;
        }

        if ($hidden === null) {
            return [array_values($ordered), $defaultHidden];
        }

        $normalizedHidden = [];

        if (! is_array($hidden) || ! array_is_list($hidden)) {
            $this->fail('hidden', 'must be a list of columns.', null);

            return [array_values($ordered), $defaultHidden];
        }

        foreach ($hidden as $name) {
            if (is_string($name) && in_array($name, $readable, true) && $this->schema->columns[$name]->isHideable()) {
                $normalizedHidden[$name] = $name;
            } else {
                $this->fail('hidden', 'names a column that cannot be hidden.', null);
            }
        }

        // Columns added to the definition after this layout was saved start with their declared default.
        if ($order !== null && $known !== []) {
            foreach ($defaultHidden as $name) {
                if (! isset($known[$name])) {
                    $normalizedHidden[$name] = $name;
                }
            }
        }

        return [array_values($ordered), array_values($normalizedHidden)];
    }

    private function density(mixed $density): string
    {
        if ($density === null || $density === '') {
            return $this->schema->table->density();
        }

        return is_string($density) && in_array($density, Table::Densities, true) ? $density : $this->fail('density', 'must be comfortable or compact.', $this->schema->table->density());
    }

    /**
     * @template T
     *
     * @param  T  $fallback
     * @return T
     */
    private function fail(string $parameter, string $reason, mixed $fallback): mixed
    {
        if ($this->strict) {
            throw InvalidTableStateException::invalid($parameter, $reason);
        }

        $this->dropped[] = $parameter;

        return $fallback;
    }
}
