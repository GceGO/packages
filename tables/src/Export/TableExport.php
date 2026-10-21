<?php

declare(strict_types=1);

namespace Gcego\Tables\Export;

use Gcego\Data\DataManager;
use Gcego\Data\Query\QueryContext;
use Gcego\Data\Query\QueryInput;
use Gcego\Data\Source;
use Gcego\Tables\Schema\TableSchema;
use Gcego\Tables\TablesConfig;
use Generator;

/**
 * An authorized export of a table: the current user's filters, search and
 * sorting, readable exportable columns only, fetched through gcego/data in
 * chunks (simple pagination, no COUNT), so memory stays bounded.
 *
 * Built by TableManager::export() for the requesting user. A queued export
 * builds it again inside SecurityManager::runOperation() so authorization
 * and tenant scope are re-checked when the job runs; downloads re-check with
 * SecurityManager::authorizeDownload(). Writers (CSV here, spreadsheets in
 * later packages) only receive plain values: never HTML, never hidden columns.
 */
final readonly class TableExport
{
    /**
     * @param  list<string>  $columns
     */
    public function __construct(
        private DataManager $data,
        private Source $source,
        private TableSchema $schema,
        private QueryInput $input,
        private QueryContext $context,
        public array $columns,
        private TablesConfig $config,
    ) {}

    /**
     * Translated column labels, in export order.
     *
     * @return list<string>
     */
    public function headings(): array
    {
        return array_map(fn (string $name): string => (string) __($this->schema->columns[$name]->label()), $this->columns);
    }

    /**
     * Rows of plain values, chunk by chunk.
     *
     * @return Generator<int, list<string|int|float|null>>
     */
    public function rows(?int $limit = null): Generator
    {
        $page = 1;
        $emitted = 0;

        do {
            $result = $this->data->query($this->source, new QueryInput(
                filters: $this->input->filters,
                search: $this->input->search,
                sorts: $this->input->sorts,
                page: $page,
                perPage: $this->config->exportChunk,
            ), $this->context);

            foreach ($result->items() as $record) {
                if ($limit !== null && $emitted >= $limit) {
                    return;
                }

                $row = [];

                foreach ($this->columns as $name) {
                    $column = $this->schema->columns[$name];
                    $value = $column->value($record);
                    $row[] = $column->forRow($value, $record)->exportValue($value, $record, $this->config);
                }

                $emitted++;

                yield $row;
            }

            $page++;
        } while ($result->pagination->hasMorePages);
    }
}
