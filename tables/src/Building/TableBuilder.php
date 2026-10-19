<?php

declare(strict_types=1);

namespace Gcego\Tables\Building;

use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\Contracts\Buildable;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Data\Pagination\PaginationMode;
use Gcego\Data\Sorting\Sort;
use Gcego\Data\Sorting\SortDirection;
use Gcego\Tables\Configuration\Pagination;
use Gcego\Tables\Configuration\Search;
use Gcego\Tables\Table;

/**
 * The fluent form of a Table:
 *
 *     Table::define()
 *         ->search('Search by name')
 *         ->pagination(25, options: [10, 25, 50])
 *         ->columns([Text::define('name', 'Name')->sortable()])
 *         ->filters([SelectFilter::define('status', 'Status')->options(Status::class)])
 *         ->actions([BulkDelete::define()])
 *
 * Every Table property is also available by name: exportable(), urlState(),
 * multiSort(), density('compact'), poll(30).
 */
class TableBuilder extends ComponentBuilder
{
    /**
     * The heading (and optional description) shown when there are no rows.
     */
    public function emptyState(string $heading, ?string $description = null): static
    {
        $builder = $this->set(Table::EmptyHeading, $heading);

        return $description === null ? $builder : $builder->set(Table::EmptyDescription, $description);
    }

    public function search(?string $placeholder = null, ?int $debounceMs = null): static
    {
        return $this->replaceConfiguration(new Search($placeholder, $debounceMs));
    }

    /**
     * @param  list<int>  $options  Page sizes offered to users ([] uses the configured options).
     */
    public function pagination(int $perPage = 25, array $options = [], PaginationMode $mode = PaginationMode::Pages): static
    {
        return $this->replaceConfiguration(new Pagination($perPage, $options, $mode));
    }

    /**
     * Add a default sort (call again to sort by further columns).
     */
    public function defaultSort(string $field, SortDirection|string $direction = SortDirection::Ascending): static
    {
        return $this->configure(new Sort($field, $direction));
    }

    /**
     * @param  iterable<ComponentInterface|Buildable|null>  $columns
     */
    public function columns(iterable $columns): static
    {
        return $this->schema($columns);
    }

    /**
     * @param  iterable<ComponentInterface|Buildable|null>  $filters
     */
    public function filters(iterable $filters): static
    {
        return $this->schema($filters);
    }

    /**
     * Header, row and bulk actions (each is placed by its own placement).
     *
     * @param  iterable<ComponentInterface|Buildable|null>  $actions
     */
    public function actions(iterable $actions): static
    {
        return $this->schema($actions);
    }
}
