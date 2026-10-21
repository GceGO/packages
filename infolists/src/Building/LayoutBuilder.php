<?php

declare(strict_types=1);

namespace Gcego\Infolists\Building;

use Closure;
use Gcego\Components\Building\ComponentBuilder;
use Gcego\Infolists\Configuration\VisibleWhen;

/**
 * The fluent form of an infolist layout:
 *
 *     Section::make('Grading')->columns(2)->visible(fn (Subject $record): bool => $record->is_graded)->schema([...])
 */
class LayoutBuilder extends ComponentBuilder
{
    /**
     * Show the layout (and resolve its entries) only while the closure returns true for the record.
     */
    public function visible(Closure $condition): static
    {
        return $this->configure(VisibleWhen::computed($condition));
    }

    public function hidden(Closure $condition): static
    {
        return $this->configure(VisibleWhen::computed($condition, negate: true));
    }
}
