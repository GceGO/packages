<?php

declare(strict_types=1);

namespace Gcego\Forms\Layout;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;

/**
 * A responsive grid (one column on small screens):
 *
 *     new Grid(2, new TextInput('name', 'Subject Name'), new Select('category', 'Category'))
 *
 * Fields may span columns with Field::ColumnSpan.
 */
final class Grid extends LayoutComponent
{
    public const string Columns = 'columns';

    public function view(): string
    {
        return 'gcego-forms::layouts.grid';
    }

    public function columns(): int
    {
        $columns = $this->get(self::Columns);

        return is_int($columns) ? $columns : 2;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(new PropertyDefinition(self::Columns, Type::int(), default: 2, rule: static fn (mixed $value): bool => is_int($value) && $value >= 1 && $value <= 4, ruleDescription: 'between 1 and 4'));
    }

    protected static function shorthand(): array
    {
        return [self::Columns];
    }
}
