<?php

declare(strict_types=1);

namespace Gcego\Tables\Actions;

use Gcego\Actions\Action;
use Gcego\Actions\ActionIntent;
use Gcego\Actions\ActionManager;
use Gcego\Actions\ActionPlacement;
use Gcego\Actions\Resolution\ResolvedAction;
use Gcego\Tables\Query\ResolvedTable;

/**
 * The blueprint's actions as the table places them:
 *
 * - header: Header and Toolbar placements (Create, export, custom), shown by an ActionHost;
 * - row: the Row placement (View, Edit, Delete, Restore, custom), per record;
 * - bulk: the Bulk placement, for the selection.
 *
 * Visibility is decided here for display (definition-level for bulk,
 * per loaded record for rows, without extra queries); execution always goes
 * through gcego/actions, which re-resolves records and re-authorizes.
 */
final readonly class TableActions
{
    public function __construct(private ActionManager $actions) {}

    /**
     * @return list<ResolvedAction>
     */
    public function row(ResolvedTable $table): array
    {
        return $this->actions->actions($table->definition, ActionPlacement::Row);
    }

    /**
     * Bulk actions of the table (for validating requests).
     *
     * @return list<ResolvedAction>
     */
    public function bulk(ResolvedTable $table): array
    {
        return $this->actions->actions($table->definition, ActionPlacement::Bulk);
    }

    /**
     * Bulk actions to offer: those the user may run on at least one loaded
     * row (bulk abilities are per record). Execution re-checks every key.
     *
     * @param  list<ResolvedAction>  $actions
     * @param  iterable<mixed>  $records
     * @return list<ResolvedAction>
     */
    public function offeredBulk(array $actions, iterable $records): array
    {
        $offered = [];

        foreach ($actions as $action) {
            if ($action->action->isDisabled() || ! $this->actions->allows($action)) {
                continue;
            }

            foreach ($records as $record) {
                if ($this->actions->allowsLoaded($action, $record)) {
                    $offered[] = $action;

                    break;
                }
            }
        }

        return $offered;
    }

    public function hasHeader(ResolvedTable $table): bool
    {
        return $this->actions->actions($table->definition, ActionPlacement::Header) !== [] || $this->actions->actions($table->definition, ActionPlacement::Toolbar) !== [];
    }

    /**
     * The row actions offered for one loaded row, as view data.
     *
     * @param  list<ResolvedAction>  $actions
     * @return list<array{name: string, label: string, icon: string|null, destructive: bool}>
     */
    public function forRecord(array $actions, mixed $record): array
    {
        $offered = [];

        foreach ($actions as $action) {
            if ($action->action->isDisabled() || ! $this->actions->allowsLoaded($action, $record)) {
                continue;
            }

            $icon = $action->action->get(Action::Icon);
            $offered[] = [
                'name' => $action->name(),
                'label' => (string) __($action->action->label()),
                'icon' => is_string($icon) ? $icon : null,
                'destructive' => $action->action->intent() === ActionIntent::Destructive,
            ];
        }

        return $offered;
    }

    /**
     * Whether the name is one of this table's row or bulk actions (requests naming anything else are ignored).
     */
    public function isRowAction(ResolvedTable $table, string $name): bool
    {
        return in_array($name, array_map(static fn (ResolvedAction $action): string => $action->name(), $this->row($table)), true);
    }

    public function isBulkAction(ResolvedTable $table, string $name): bool
    {
        return in_array($name, array_map(static fn (ResolvedAction $action): string => $action->name(), $this->bulk($table)), true);
    }
}
