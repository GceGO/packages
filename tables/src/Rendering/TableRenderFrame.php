<?php

declare(strict_types=1);

namespace Gcego\Tables\Rendering;

use Gcego\Data\Results\PaginationResult;
use Gcego\Tables\Query\ColumnAccess;
use Gcego\Tables\Query\ResolvedTable;
use Gcego\Tables\Selection\Selection;
use Gcego\Tables\State\TableState;

/**
 * Everything one render of a table needs, computed by the host before
 * rendering (renderers never query).
 */
final readonly class TableRenderFrame
{
    /**
     * @param  list<array{key: string|null, cells: array<string, array<string, mixed>>, actions: list<array{name: string, label: string, icon: string|null, destructive: bool}>, selected: bool}>  $rows
     * @param  list<array{name: string, label: string, destructive: bool}>  $bulkActions
     * @param  list<array{id: string, name: string, default: bool}>  $views
     * @param  list<int>  $perPageOptions
     * @param  list<array{name: string, text: string}>  $indicators
     */
    public function __construct(
        public ResolvedTable $table,
        public TableState $state,
        public ColumnAccess $access,
        public array $rows,
        public ?PaginationResult $pagination,
        public string $hostId,
        public bool $live = true,
        public bool $error = false,
        public ?string $notice = null,
        public ?Selection $selection = null,
        public bool $selectable = false,
        public array $bulkActions = [],
        public bool $hasRowActions = false,
        public bool $hasHeaderActions = false,
        public bool $columnManager = false,
        public bool $savedViews = false,
        public array $views = [],
        public ?string $activeView = null,
        public array $perPageOptions = [],
        public array $indicators = [],
        public bool $searchable = false,
        public int $debounceMs = 350,
        public bool $multiSort = false,
        public bool $exportable = false,
        public ?int $poll = null,
    ) {}

    public function id(string $suffix): string
    {
        return $this->hostId.'-'.$suffix;
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }
}
