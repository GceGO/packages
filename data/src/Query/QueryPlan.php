<?php

declare(strict_types=1);

namespace Gcego\Data\Query;

use Closure;
use Gcego\Data\Filters\Filter;
use Gcego\Data\Pagination\PaginationMode;
use Gcego\Data\Sorting\SortDirection;

/**
 * Validated request input, expressed only in trusted terms (columns from the
 * definition, cast values, capped page sizes). Providers execute plans; they
 * never see raw input.
 */
final readonly class QueryPlan
{
    /**
     * @param  list<Filter>  $filters  Request filters, already resolved to trusted columns and cast values.
     * @param  list<string>  $terms  Search terms.
     * @param  list<array{column: string, direction: SortDirection}>  $sorts  Final ordering, defaults included.
     * @param  array<string, Closure|null>  $includes  Requested, allowlisted relations.
     * @param  array<string, list<string>>  $applied  Request keys that took effect, for metadata: filter, sort, include.
     */
    public function __construct(
        public array $filters = [],
        public array $terms = [],
        public array $sorts = [],
        public array $includes = [],
        public PaginationMode $mode = PaginationMode::Pages,
        public int $perPage = 25,
        public int $page = 1,
        public ?string $cursor = null,
        public array $applied = [],
    ) {}
}
