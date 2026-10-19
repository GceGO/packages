<?php

declare(strict_types=1);

namespace Gcego\Tables\Configuration;

use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Data\Pagination\PaginationMode;
use Gcego\Tables\Exceptions\InvalidTableException;

/**
 * Page size and mode, executed by gcego/data at the database:
 *
 *     new Pagination(25)
 *     new Pagination(50, options: [25, 50, 100])
 *     new Pagination(50, mode: PaginationMode::Simple)    // no COUNT query
 *     new Pagination(50, mode: PaginationMode::Cursor)    // keyset; needs sortable, unique ordering
 *
 * Sizes are capped by gcego-tables.pagination.max_per_page.
 */
final readonly class Pagination implements ConfigurationNode
{
    /**
     * @param  list<int>  $options  Page sizes offered to users ([] uses the configured options).
     */
    public function __construct(
        public int $perPage = 25,
        public array $options = [],
        public PaginationMode $mode = PaginationMode::Pages,
    ) {
        if ($perPage < 1 || $perPage > 500) {
            throw InvalidTableException::invalid('Pagination', 'perPage must be between 1 and 500.');
        }

        foreach ($options as $option) {
            if (! is_int($option) || $option < 1 || $option > 500) {
                throw InvalidTableException::invalid('Pagination', 'options must be page sizes between 1 and 500.');
            }
        }
    }

    /**
     * @return array{per_page: int, options: list<int>, mode: string}
     */
    public function normalize(): array
    {
        return ['per_page' => $this->perPage, 'options' => $this->options, 'mode' => $this->mode->value];
    }
}
