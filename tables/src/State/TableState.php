<?php

declare(strict_types=1);

namespace Gcego\Tables\State;

/**
 * Validated table state for one request: search, filters, sorting,
 * pagination and column layout. Everything here has been checked against
 * the table definition and the user's readable columns by TableStateNormalizer.
 *
 * Only small scalars: no records, models or queries ever live in state.
 */
final readonly class TableState
{
    public const int Version = 1;

    /**
     * @param  array<string, mixed>  $filters  filter name => normalized, active state
     * @param  list<array{column: string, direction: 'asc'|'desc'}>  $sorts
     * @param  list<string>  $order  Column names in display order (readable columns only).
     * @param  list<string>  $hidden  Hidden column names.
     */
    public function __construct(
        public ?string $search = null,
        public array $filters = [],
        public array $sorts = [],
        public int $page = 1,
        public int $perPage = 25,
        public ?string $cursor = null,
        public array $order = [],
        public array $hidden = [],
        public string $density = 'comfortable',
    ) {}

    /**
     * Visible columns, in order.
     *
     * @return list<string>
     */
    public function visibleColumns(): array
    {
        return array_values(array_diff($this->order, $this->hidden));
    }

    public function isFiltered(): bool
    {
        return $this->search !== null || $this->filters !== [];
    }

    public function sortDirection(string $column): ?string
    {
        foreach ($this->sorts as $sort) {
            if ($sort['column'] === $column) {
                return $sort['direction'];
            }
        }

        return null;
    }

    public function sortPosition(string $column): ?int
    {
        foreach ($this->sorts as $index => $sort) {
            if ($sort['column'] === $column) {
                return $index + 1;
            }
        }

        return null;
    }

    /**
     * What a saved view stores (no page, cursor or selection).
     *
     * @return array{search: string|null, filters: array<string, mixed>, sorts: list<array{column: string, direction: 'asc'|'desc'}>, per_page: int, order: list<string>, hidden: list<string>, density: string}
     */
    public function toView(): array
    {
        return [
            'search' => $this->search,
            'filters' => $this->filters,
            'sorts' => $this->sorts,
            'per_page' => $this->perPage,
            'order' => $this->order,
            'hidden' => $this->hidden,
            'density' => $this->density,
        ];
    }

    /**
     * What column preferences store.
     *
     * @return array{order: list<string>, hidden: list<string>, per_page: int, density: string}
     */
    public function toPreferences(): array
    {
        return ['order' => $this->order, 'hidden' => $this->hidden, 'per_page' => $this->perPage, 'density' => $this->density];
    }
}
