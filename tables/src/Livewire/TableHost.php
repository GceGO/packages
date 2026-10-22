<?php

declare(strict_types=1);

namespace Gcego\Tables\Livewire;

use Gcego\Actions\ActionIntent;
use Gcego\Data\Exceptions\InvalidQueryException;
use Gcego\Data\Results\DataResult;
use Gcego\Data\Source;
use Gcego\Rendering\Context\RenderMode;
use Gcego\Rendering\RenderEngine;
use Gcego\Tables\Actions\TableActions;
use Gcego\Tables\Exceptions\InvalidTableStateException;
use Gcego\Tables\Export\CsvWriter;
use Gcego\Tables\Preferences\ColumnPreferences;
use Gcego\Tables\Query\ColumnAccess;
use Gcego\Tables\Query\ResolvedTable;
use Gcego\Tables\Rendering\TableRenderContext;
use Gcego\Tables\Rendering\TableRenderFrame;
use Gcego\Tables\Rendering\TableRows;
use Gcego\Tables\Selection\Selection;
use Gcego\Tables\State\TableState;
use Gcego\Tables\Table;
use Gcego\Tables\TableManager;
use Gcego\Tables\Views\SavedViewManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * <livewire:gcego-table definition="subjects" />
 *
 * Hosts the Table of a registered blueprint. The definition is #[Locked]
 * and resolved through the registry on every request (never a class or a
 * model from the client), the blueprint's gcego/security requirements are
 * checked on every request, and every query runs through gcego/data with
 * the current user, so policies, tenant scopes and mandatory constraints
 * always apply.
 *
 * Client state ($search, $filters, $sort, $page, $perPage, $selected) is
 * untrusted: it is normalized against the definition and the user's column
 * access on every render; unknown filters are refused on update. Column
 * layout ($order, $hidden, $density) and the active view change only
 * through methods, which persist preferences. Rows and models are never
 * public state: the snapshot only carries these small values.
 *
 * Actions run through gcego/actions: header actions in an ActionHost, row
 * and bulk actions through a button-less ActionHost on this table's
 * channel, which re-resolves records (scoped) and re-authorizes them.
 */
final class TableHost extends Component
{
    #[Locked]
    public string $definition = '';

    public string $search = '';

    /**
     * @var array<string, mixed>
     */
    public array $filters = [];

    /** "name,-code" */
    public string $sort = '';

    public int $page = 1;

    /** 0: the table's default page size. */
    public int $perPage = 0;

    #[Locked]
    public ?string $cursor = null;

    /**
     * Untrusted selected row keys.
     *
     * @var list<string>
     */
    public array $selected = [];

    /**
     * @var list<string>
     */
    #[Locked]
    public array $order = [];

    /**
     * @var list<string>
     */
    #[Locked]
    public array $hidden = [];

    #[Locked]
    public string $density = '';

    #[Locked]
    public ?string $view = null;

    #[Locked]
    public ?string $notice = null;

    private ?ResolvedTable $resolvedTable = null;

    private ?ColumnAccess $columnAccess = null;

    public function mount(string $definition): void
    {
        $tables = app(TableManager::class);
        $table = $tables->resolve($definition);
        $this->definition = $table->definition;
        $tables->authorize($table);
        $access = $this->access();
        $defaults = $tables->normalizer($table->schema, $access, model: $tables->model($table))->defaults();
        $lenient = $tables->normalizer($table->schema, $access, strict: false, model: $tables->model($table));
        $preferences = app(ColumnPreferences::class)->load($table, $tables->owner(), $lenient);

        $this->filters = $this->filters === [] ? $defaults->filters : $this->filters;
        $this->order = self::names($preferences['order'] ?? null) ?? $defaults->order;
        $this->hidden = self::names($preferences['hidden'] ?? null) ?? $defaults->hidden;
        $this->density = is_string($preferences['density'] ?? null) ? $preferences['density'] : $defaults->density;
        $this->perPage = is_int($preferences['per_page'] ?? null) && $preferences['per_page'] !== $defaults->perPage ? $preferences['per_page'] : 0;

        if ($this->search === '' && $this->sort === '' && $this->filters === $defaults->filters) {
            $default = app(SavedViewManager::class)->default($table, $tables->owner());

            if ($default !== null) {
                $this->applyView($table, $default->id);
            }
        }
    }

    /**
     * Legacy Livewire query-string support, enabled per table with Table::UrlState.
     *
     * @return array<string, array{as: string, except?: mixed, history?: bool}>
     */
    protected function queryString(): array
    {
        try {
            $table = $this->definition === '' ? null : $this->table();
        } catch (Throwable) {
            return [];
        }

        if ($table === null || $table->table->get(Table::UrlState) !== true) {
            return [];
        }

        $prefix = $table->table->tableName().'_';

        return [
            'search' => ['as' => $prefix.'search', 'except' => ''],
            'filters' => ['as' => $prefix.'filters', 'except' => []],
            'sort' => ['as' => $prefix.'sort', 'except' => ''],
            'page' => ['as' => $prefix.'page', 'except' => 1],
        ];
    }

    /**
     * Every client update must address declared state.
     */
    public function updating(string $property, mixed $value): void
    {
        $root = explode('.', $property)[0];

        if ($root === 'filters') {
            $name = explode('.', $property)[1] ?? null;

            if ($name === null) {
                if (! is_array($value) || array_diff(array_map(strval(...), array_keys($value)), array_keys($this->table()->schema->filters)) !== []) {
                    throw InvalidTableStateException::invalid('filters', 'names a filter this table does not have.');
                }
            } elseif ($this->table()->schema->filter($name) === null || ! app(TableManager::class)->normalizer($this->table()->schema, $this->access(), model: app(TableManager::class)->model($this->table()))->filterAllowed($name)) {
                throw InvalidTableStateException::invalid('filters.'.$name, 'is not a filter of this table.');
            }
        }

        if ($root === 'search' && (! is_string($value) || mb_strlen($value) > 500)) {
            throw InvalidTableStateException::invalid('search', 'must be a short text.');
        }

        if ($root === 'selected' && (! is_array($value) && ! is_string($value) && ! is_int($value))) {
            throw InvalidTableStateException::invalid('selected', 'must be a list of row keys.');
        }
    }

    public function updated(string $property): void
    {
        $root = explode('.', $property)[0];

        if (in_array($root, ['search', 'filters'], true)) {
            $this->resetPosition();
            $this->selected = [];
            $this->view = null;
        }

        if ($root === 'sort') {
            $this->resetPosition();
        }

        if ($root === 'perPage') {
            $this->resetPosition();
            $this->savePreferences();
        }

        if ($root === 'selected') {
            $this->selected = Selection::from($this->selected, app(TableManager::class)->config()->maxSelection)->keys;
        }
    }

    public function sortBy(string $column, bool $add = false): void
    {
        $table = $this->table();
        $sortable = $table->schema->sortable();

        if (! isset($sortable[$column]) || ! $this->access()->canRead($column)) {
            throw InvalidTableStateException::invalid('sort', 'names a column that cannot be sorted.');
        }

        $sorts = $this->sorts();
        $current = null;

        foreach ($sorts as $sort) {
            if ($sort['column'] === $column) {
                $current = $sort['direction'];
            }
        }

        $next = match ($current) {
            null => 'asc',
            'asc' => 'desc',
            default => null,
        };
        $others = $add && $table->schema->multiSort() ? array_values(array_filter($sorts, static fn (array $sort): bool => $sort['column'] !== $column)) : [];
        $sorts = $next === null ? $others : [...$others, ['column' => $column, 'direction' => $next]];
        $this->sort = implode(',', array_map(static fn (array $sort): string => ($sort['direction'] === 'desc' ? '-' : '').$sort['column'], array_slice($sorts, -3)));
        $this->resetPosition();
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, min($page, 1_000_000));
        $this->cursor = null;
    }

    public function nextPage(): void
    {
        $this->gotoPage($this->page + 1);
    }

    public function previousPage(): void
    {
        $this->gotoPage($this->page - 1);
    }

    /**
     * Cursor pagination: the cursor comes from the last result and is decoded (and checked) by gcego/data.
     */
    public function gotoCursor(?string $cursor): void
    {
        $this->cursor = $cursor === null || $cursor === '' ? null : (strlen($cursor) <= 2048 && preg_match('/^[A-Za-z0-9_\-=]+$/', $cursor) === 1 ? $cursor : throw InvalidTableStateException::invalid('cursor', 'is not a valid cursor.'));
    }

    public function resetSearch(): void
    {
        $this->search = '';
        $this->resetPosition();
        $this->selected = [];
    }

    public function removeFilter(string $name): void
    {
        unset($this->filters[$name]);
        $this->resetPosition();
        $this->selected = [];
        $this->view = null;
    }

    public function resetFilters(): void
    {
        $this->filters = [];
        $this->resetPosition();
        $this->selected = [];
        $this->view = null;
    }

    public function resetTable(): void
    {
        $table = $this->table();
        $defaults = app(TableManager::class)->normalizer($table->schema, $this->access(), model: app(TableManager::class)->model($table))->defaults();
        $this->search = '';
        $this->filters = $defaults->filters;
        $this->sort = '';
        $this->selected = [];
        $this->view = null;
        $this->notice = null;
        $this->resetPosition();
    }

    /**
     * Adds the rows of the current page to the selection (never more than selection.max_records).
     */
    public function selectPage(): void
    {
        $state = $this->state($this->table(), $this->access());
        $result = $this->query($this->table(), $this->access(), $state);
        $keys = [];

        foreach ($result->items() as $record) {
            $keyName = $this->table()->source->get(Source::Key);
            $key = TableRows::key($record, is_string($keyName) ? $keyName : null);

            if ($key !== null) {
                $keys[] = $key;
            }
        }

        $selection = Selection::from($this->selected, app(TableManager::class)->config()->maxSelection)->with($keys, app(TableManager::class)->config()->maxSelection);
        $this->selected = $selection->keys;
        $this->notice = $selection->truncated ? (string) __('gcego-tables::messages.selection_limit', ['max' => app(TableManager::class)->config()->maxSelection]) : null;
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    /**
     * A row action for one record key (untrusted): gcego/actions re-resolves the record in scope and re-authorizes.
     */
    public function runAction(string $action, string $record): void
    {
        if (! app(TableActions::class)->isRowAction($this->table(), $action)) {
            throw InvalidTableStateException::invalid('action', 'is not a row action of this table.');
        }

        if ($record === '' || strlen($record) > 191) {
            throw InvalidTableStateException::invalid('record', 'is not a row key.');
        }

        $this->dispatch('gcego-actions-run', channel: $this->getId(), action: $action, record: $record);
    }

    /**
     * A bulk action for the selection (bounded; every key is re-checked by gcego/actions).
     */
    public function runBulkAction(string $action): void
    {
        if (! app(TableActions::class)->isBulkAction($this->table(), $action)) {
            throw InvalidTableStateException::invalid('action', 'is not a bulk action of this table.');
        }

        $selection = Selection::from($this->selected, app(TableManager::class)->config()->maxSelection);

        if ($selection->count() === 0) {
            $this->notice = (string) __('gcego-tables::messages.nothing_selected');

            return;
        }

        $this->dispatch('gcego-actions-run', channel: $this->getId(), action: $action, keys: $selection->keys);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    #[On('gcego-action-completed')]
    public function actionCompleted(array $result = []): void
    {
        if (($result['status'] ?? null) === 'success') {
            $this->selected = [];
        }
    }

    public function refresh(): void
    {
        $this->notice = null;
    }

    public function toggleColumn(string $column): void
    {
        $table = $this->table();
        $definition = $table->schema->column($column);

        if ($definition === null || ! $definition->isHideable() || (! $this->access()->canRead($column) && ! $this->access()->isMasked($column))) {
            throw InvalidTableStateException::invalid('columns', 'names a column that cannot be hidden.');
        }

        $hidden = in_array($column, $this->hidden, true) ? array_values(array_diff($this->hidden, [$column])) : [...$this->hidden, $column];
        $this->hidden = $this->layout(order: $this->order, hidden: $hidden)->hidden;
        $this->savePreferences();
    }

    public function moveColumn(string $column, int $offset): void
    {
        $order = $this->layout()->order;
        $index = array_search($column, $order, true);

        if ($index === false || ! in_array($offset, [-1, 1], true)) {
            throw InvalidTableStateException::invalid('columns', 'names a column that cannot be moved.');
        }

        $target = $index + $offset;

        if ($target < 0 || $target >= count($order)) {
            return;
        }

        [$order[$index], $order[$target]] = [$order[$target], $order[$index]];
        $this->order = $this->layout(order: array_values($order))->order;
        $this->savePreferences();
    }

    public function resetColumns(): void
    {
        $table = $this->table();
        $defaults = app(TableManager::class)->normalizer($table->schema, $this->access(), model: app(TableManager::class)->model($table))->defaults();
        $this->order = $defaults->order;
        $this->hidden = $defaults->hidden;
        $this->density = $defaults->density;
        $this->perPage = 0;
        app(ColumnPreferences::class)->reset($table, app(TableManager::class)->owner());
    }

    public function setDensity(string $density): void
    {
        if (! in_array($density, Table::Densities, true)) {
            throw InvalidTableStateException::invalid('density', 'must be comfortable or compact.');
        }

        $this->density = $density;
        $this->savePreferences();
    }

    public function saveView(string $name): void
    {
        $table = $this->table();
        $owner = app(TableManager::class)->owner();
        $views = app(SavedViewManager::class);

        if ($owner === null || ! $views->enabled($table)) {
            return;
        }

        $view = $views->save($table, $owner, $name, $this->state($table, $this->access()));
        $this->view = $view->id;
        $this->notice = (string) __('gcego-tables::messages.view_saved', ['name' => $view->name]);
    }

    public function loadView(string $id): void
    {
        $this->applyView($this->table(), $id);
    }

    public function renameView(string $id, string $name): void
    {
        $owner = app(TableManager::class)->owner();

        if ($owner !== null) {
            app(SavedViewManager::class)->rename($this->table(), $owner, $id, $name);
        }
    }

    public function deleteView(string $id): void
    {
        $owner = app(TableManager::class)->owner();

        if ($owner !== null && app(SavedViewManager::class)->delete($this->table(), $owner, $id) && $this->view === $id) {
            $this->view = null;
        }
    }

    public function setDefaultView(?string $id = null): void
    {
        $owner = app(TableManager::class)->owner();

        if ($owner !== null) {
            app(SavedViewManager::class)->setDefault($this->table(), $owner, $id);
        }
    }

    /**
     * Download the rows the user is looking at as CSV: the current search, filters and sorting,
     * visible readable and exportable columns only, at most gcego-tables.export.max_rows rows.
     * The table, its blueprint requirements and the user's column access are re-checked here,
     * and rows are read through gcego/data as the current user (TableManager::export()).
     */
    public function export(): StreamedResponse
    {
        $tables = app(TableManager::class);
        $table = $this->table();

        if ($table->table->get(Table::Exportable) !== true) {
            throw InvalidTableStateException::invalid('export', 'is not offered by this table.');
        }

        $layout = $this->layout();
        $export = $tables->export($this->definition, [
            'search' => $this->search,
            'filters' => $this->filters,
            'sorts' => $this->sorts(),
            'order' => $layout->order,
            'hidden' => $layout->hidden,
        ]);
        $writer = new CsvWriter($tables->config()->exportMaxRows);
        $label = $table->table->get(Table::Label);
        $base = Str::slug(is_string($label) && $label !== '' ? $label : ($table->blueprint->name() ?? $table->table->tableName()));
        $filename = ($base === '' ? 'table' : $base).'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(static function () use ($writer, $export): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                return;
            }

            fwrite($stream, "\u{FEFF}");   // BOM: spreadsheets read UTF-8 names and accents correctly
            $writer->write($export, $stream);
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(): View
    {
        $tables = app(TableManager::class);
        $table = $this->table();
        $tables->authorize($table);
        $access = $this->access();

        try {
            $state = $this->state($table, $access);
        } catch (InvalidTableStateException) {
            $this->search = '';
            $this->filters = [];
            $this->sort = '';
            $this->resetPosition();
            $this->perPage = 0;
            $this->notice = (string) __('gcego-tables::messages.state_reset');
            $state = $this->state($table, $access);
        }

        $error = false;
        $result = null;

        try {
            $result = $this->query($table, $access, $state);

            if ($result->isEmpty() && $state->page > 1 && $result->pagination->lastPage !== null) {
                $this->page = max(1, $result->pagination->lastPage);
                $state = $this->state($table, $access);
                $result = $this->query($table, $access, $state);
            }
        } catch (AuthorizationException|HttpExceptionInterface $exception) {
            if ($exception instanceof InvalidQueryException || $exception instanceof InvalidTableStateException) {
                $this->resetTable();
                $this->notice = (string) __('gcego-tables::messages.state_reset');
                $state = $this->state($table, $access);
                $result = $this->query($table, $access, $state);
            } else {
                throw $exception;
            }
        } catch (Throwable $exception) {
            report($exception);
            $error = true;
        }

        $actions = app(TableActions::class);
        $rowActions = $actions->row($table);
        $bulk = $actions->offeredBulk($actions->bulk($table), $result?->items() ?? []);
        $selection = Selection::from($this->selected, $tables->config()->maxSelection);
        $key = $table->source->get(Source::Key);
        $rows = $result === null ? [] : app(TableRows::class)->build($result->items(), $table->schema, $access, $state->visibleColumns(), $rowActions, $selection, is_string($key) ? $key : null);
        $selectable = $table->table->get(Table::Selectable) ?? $bulk !== [];
        $indicators = [];

        foreach ($state->filters as $name => $value) {
            $text = $table->schema->filter($name)?->indicator($value, $tables->model($table));

            if ($text !== null) {
                $indicators[] = ['name' => $name, 'text' => $text];
            }
        }

        $owner = $tables->owner();
        $views = app(SavedViewManager::class);
        $normalizer = $tables->normalizer($table->schema, $access, model: $tables->model($table));
        $frame = new TableRenderFrame(
            table: $table,
            state: $state,
            access: $access,
            rows: $rows,
            pagination: $result?->pagination,
            hostId: is_string($id = $this->getId()) ? $id : 'gcego-table',
            error: $error,
            notice: $this->notice,
            selection: $selection,
            selectable: $selectable === true,
            bulkActions: array_map(static fn ($action): array => ['name' => $action->name(), 'label' => (string) __($action->action->label()), 'destructive' => $action->action->intent() === ActionIntent::Destructive], $bulk),
            hasRowActions: $rowActions !== [],
            hasHeaderActions: $actions->hasHeader($table),
            columnManager: $table->table->get(Table::ColumnManager) !== false,
            savedViews: $owner !== null && $views->enabled($table),
            views: array_map(static fn ($view): array => $view->summary(), $views->all($table, $owner)),
            activeView: $this->view,
            perPageOptions: $normalizer->perPageOptions(),
            indicators: $indicators,
            searchable: $normalizer->searchable(),
            debounceMs: $table->schema->search->debounceMs ?? $tables->config()->searchDebounceMs,
            multiSort: $table->schema->multiSort(),
            exportable: $table->table->get(Table::Exportable) === true,
            poll: is_int($poll = $table->table->get(Table::Poll)) ? $poll : null,
        );

        $content = app(TableRenderContext::class)->run($frame, fn (): string => app(RenderEngine::class)->render($table->table, RenderMode::Livewire, identity: $table->definition)->toHtml());

        return view('gcego-tables::tables.host', ['content' => new HtmlString($content), 'poll' => $frame->poll]);
    }

    private function query(ResolvedTable $table, ColumnAccess $access, TableState $state): DataResult
    {
        return app(TableManager::class)->query($table, $access, $state);
    }

    private function state(ResolvedTable $table, ColumnAccess $access): TableState
    {
        $tables = app(TableManager::class);
        $layout = $this->layout();

        return $tables->normalizer($table->schema, $access, model: $tables->model($table))->normalize([
            'search' => $this->search,
            'filters' => $this->filters,
            'sorts' => $this->sorts(),
            'page' => $this->page,
            'per_page' => $this->perPage === 0 ? null : $this->perPage,
            'cursor' => $this->cursor,
            'order' => $layout->order,
            'hidden' => $layout->hidden,
            'density' => $layout->density,
        ]);
    }

    /**
     * The column layout, leniently re-checked against the current access (permissions can change mid-session).
     *
     * @param  list<string>|null  $order
     * @param  list<string>|null  $hidden
     */
    private function layout(?array $order = null, ?array $hidden = null): TableState
    {
        $table = $this->table();

        return app(TableManager::class)->normalizer($table->schema, $this->access(), strict: false, model: app(TableManager::class)->model($table))->normalize([
            'order' => $order ?? $this->order,
            'hidden' => $hidden ?? $this->hidden,
            'density' => $this->density,
        ]);
    }

    /**
     * @return list<array{column: string, direction: string}>
     */
    private function sorts(): array
    {
        $sorts = [];

        foreach ($this->sort === '' ? [] : array_slice(explode(',', $this->sort), 0, 5) as $item) {
            $item = trim($item);
            $sorts[] = str_starts_with($item, '-') ? ['column' => substr($item, 1), 'direction' => 'desc'] : ['column' => $item, 'direction' => 'asc'];
        }

        return $sorts;
    }

    private function applyView(ResolvedTable $table, string $id): void
    {
        $tables = app(TableManager::class);
        $owner = $tables->owner();

        if ($owner === null) {
            return;
        }

        $restored = app(SavedViewManager::class)->restore($table, $owner, $id, $tables->normalizer($table->schema, $this->access(), strict: false, model: $tables->model($table)));

        if ($restored === null) {
            $this->notice = (string) __('gcego-tables::messages.view_unavailable');

            return;
        }

        [$state, $dropped] = $restored;
        $this->search = $state->search ?? '';
        $this->filters = $state->filters;
        $this->sort = implode(',', array_map(static fn (array $sort): string => ($sort['direction'] === 'desc' ? '-' : '').$sort['column'], $state->sorts));
        $this->perPage = $state->perPage;
        $this->order = $state->order;
        $this->hidden = $state->hidden;
        $this->density = $state->density;
        $this->view = $id;
        $this->selected = [];
        $this->resetPosition();
        $this->notice = $dropped === [] ? null : (string) __('gcego-tables::messages.view_partial');
    }

    private function savePreferences(): void
    {
        $table = $this->table();

        try {
            app(ColumnPreferences::class)->save($table, app(TableManager::class)->owner(), $this->state($table, $this->access()));
        } catch (InvalidTableStateException) {
            app(ColumnPreferences::class)->save($table, app(TableManager::class)->owner(), $this->layout());
        }
    }

    /**
     * @return list<string>|null
     */
    private static function names(mixed $names): ?array
    {
        return is_array($names) ? array_values(array_filter($names, is_string(...))) : null;
    }

    private function resetPosition(): void
    {
        $this->page = 1;
        $this->cursor = null;
    }

    private function table(): ResolvedTable
    {
        return $this->resolvedTable ??= app(TableManager::class)->resolve($this->definition);
    }

    private function access(): ColumnAccess
    {
        return $this->columnAccess ??= app(TableManager::class)->access($this->table()->schema);
    }
}
