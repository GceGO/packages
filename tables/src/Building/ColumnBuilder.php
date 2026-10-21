<?php

declare(strict_types=1);

namespace Gcego\Tables\Building;

use Closure;
use Gcego\Components\Building\ComponentBuilder;
use Gcego\Security\Contracts\Requirement;
use Gcego\Tables\Columns\Column;
use Gcego\Tables\Configuration\Access;
use Gcego\Tables\Configuration\Format;

/**
 * The fluent form of a table column:
 *
 *     Text::define('email', 'Email')->searchable()->copyable()->visibleFrom('md')
 *     Text::define('phone', 'Phone')->access(new Ability('viewContact', Student::class), mask: true)
 *
 * Every column property is available by name (sortable(), width('sm'),
 * hideable(false), variants([...]) on badges, and so on). Presentation
 * properties also take per-row closures injected with $state, $record,
 * $column and the record by type:
 *
 *     Text::make('name')->tooltip(fn (Subject $record): string => $record->code)
 *     Badge::make('status')->variants(fn (string $state): array => [...])
 */
class ColumnBuilder extends ComponentBuilder
{
    /**
     * Who may read the column's values; others see nothing, or a mask.
     */
    public function access(?Requirement $read = null, bool $mask = false): static
    {
        return $this->replaceConfiguration(new Access($read, $mask));
    }

    /**
     * Compute the cell value: fn (Subject $record) => $record->first_name.' '.$record->last_name.
     * Search and sort a computed column through searchable([...fields]) and sortColumn().
     */
    public function state(Closure $state): static
    {
        return $this->set(Column::StateUsing, $state);
    }

    /**
     * Format the cell text with injected parameters: fn (?int $state, Result $record): ?string.
     * The result is plain text and is always escaped.
     */
    public function formatStateUsing(Closure $formatter): static
    {
        return $this->replaceConfiguration(new Format($formatter, injected: true));
    }

    /**
     * With a closure, decide once per request whether the column exists:
     * fn (?User $user): bool => $user?->can('viewSalaries') ?? false.
     * A removed column is not queried, sorted, searched or exported.
     * With a boolean, false keeps the column but hides it until the user shows it.
     */
    public function visible(bool|Closure $condition = true): static
    {
        return $condition instanceof Closure ? $this->set(Column::VisibleUsing, $condition) : $this->set(Column::Visible, $condition);
    }

    /**
     * Remove the column for this request when the condition holds (see visible()).
     */
    public function hidden(bool|Closure $condition = true): static
    {
        return $this->set(Column::HiddenUsing, $condition instanceof Closure ? $condition : static fn (): bool => $condition);
    }

    /**
     * Format the cell text: a closure fn (mixed $value, mixed $record): ?string, or a formatter class.
     */
    public function formatUsing(Closure|string $formatter): static
    {
        return $this->replaceConfiguration(new Format($formatter));
    }
}
