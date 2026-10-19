<?php

declare(strict_types=1);

namespace Gcego\Tables\Schema;

use Gcego\Data\Sorting\Sort;
use Gcego\Tables\Columns\Column;
use Gcego\Tables\Configuration\Pagination;
use Gcego\Tables\Configuration\Search;
use Gcego\Tables\Filters\TableFilter;
use Gcego\Tables\Table;

/**
 * A compiled table definition: columns and filters by name, in order, and
 * what they allow. Built once per Table instance (TableManager caches it).
 */
final readonly class TableSchema
{
    /**
     * @param  array<string, Column>  $columns  name => column, in definition order
     * @param  array<string, TableFilter>  $filters
     * @param  list<Sort>  $defaultSorts
     */
    public function __construct(
        public Table $table,
        public array $columns,
        public array $filters,
        public array $defaultSorts,
        public ?Pagination $pagination,
        public ?Search $search,
    ) {}

    public function column(string $name): ?Column
    {
        return $this->columns[$name] ?? null;
    }

    public function filter(string $name): ?TableFilter
    {
        return $this->filters[$name] ?? null;
    }

    /**
     * @return list<string>
     */
    public function columnNames(): array
    {
        return array_keys($this->columns);
    }

    public function isSearchable(): bool
    {
        foreach ($this->columns as $column) {
            if ($column->isSearchable()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, Column> sort key => column
     */
    public function sortable(): array
    {
        $sortable = [];

        foreach ($this->columns as $column) {
            if ($column->isSortable()) {
                $sortable[$column->sortKey()] = $column;
            }
        }

        return $sortable;
    }

    public function multiSort(): bool
    {
        return $this->table->get(Table::MultiSort) === true;
    }

    public function name(): string
    {
        return $this->table->tableName();
    }
}
