<?php

declare(strict_types=1);

namespace Gcego\Tables\Schema;

use Gcego\Data\Pagination\PaginationMode;
use Gcego\Data\Sorting\Sort;
use Gcego\Tables\Columns\Column;
use Gcego\Tables\Configuration\Pagination;
use Gcego\Tables\Configuration\Search;
use Gcego\Tables\Exceptions\InvalidTableException;
use Gcego\Tables\Filters\TableFilter;
use Gcego\Tables\Table;

/**
 * Compiles a Table into a TableSchema. Pure and query-free: it rejects
 * duplicate column or filter names, a Search node without searchable
 * columns, and cursor pagination without a default sort.
 */
final class SchemaCompiler
{
    public function compile(Table $table): TableSchema
    {
        $columns = [];
        $filters = [];

        foreach ($table->children() as $child) {
            if ($child instanceof Column) {
                $name = $child->columnName();

                if (isset($columns[$name])) {
                    throw InvalidTableException::invalid(sprintf('Column "%s"', $name), 'two columns share this name.');
                }

                $columns[$name] = $child;
            }

            if ($child instanceof TableFilter) {
                $name = $child->filterName();

                if (isset($filters[$name])) {
                    throw InvalidTableException::invalid(sprintf('Filter "%s"', $name), 'two filters share this name.');
                }

                $filters[$name] = $child;
            }
        }

        if ($columns === []) {
            throw InvalidTableException::invalid('Table "'.$table->tableName().'"', 'it needs at least one column.');
        }

        $search = $table->configurations()->first(Search::class);
        $pagination = $table->configurations()->first(Pagination::class);
        $sorts = array_values(array_filter($table->configurations()->all(), static fn (mixed $node): bool => $node instanceof Sort));
        $schema = new TableSchema($table, $columns, $filters, $sorts, $pagination instanceof Pagination ? $pagination : null, $search instanceof Search ? $search : null);

        if ($schema->search !== null && ! $schema->isSearchable()) {
            throw InvalidTableException::invalid('Table "'.$table->tableName().'"', 'it has a Search node but no Searchable column.');
        }

        if ($schema->pagination?->mode === PaginationMode::Cursor && $sorts === []) {
            throw InvalidTableException::invalid('Table "'.$table->tableName().'"', 'cursor pagination needs a default Sort node.');
        }

        return $schema;
    }
}
