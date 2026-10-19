<?php

declare(strict_types=1);

namespace Gcego\Tables\Building;

use Closure;
use Gcego\Components\Building\ComponentBuilder;
use Gcego\Security\Contracts\Requirement;
use Gcego\Tables\Configuration\Access;
use Gcego\Tables\Configuration\Format;

/**
 * The fluent form of a table column:
 *
 *     Text::define('email', 'Email')->searchable()->copyable()->visibleFrom('md')
 *     Text::define('phone', 'Phone')->access(new Ability('viewContact', Student::class), mask: true)
 *
 * Every column property is available by name (sortable(), width('sm'),
 * hideable(false), variants([...]) on badges, and so on).
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
     * Format the cell text: a closure fn (mixed $value, mixed $record): ?string, or a formatter class.
     */
    public function formatUsing(Closure|string $formatter): static
    {
        return $this->replaceConfiguration(new Format($formatter));
    }
}
