<?php

declare(strict_types=1);

namespace Gcego\Navigation\Components;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;

/**
 * A menu of links behind a button (a disclosure, not an ARIA menu: its
 * entries are ordinary links). In the top navigation it opens below its
 * trigger; Escape and clicks outside close it and focus returns to the
 * trigger; arrow keys move between its links. In the sidebar and on mobile
 * it behaves like a collapsed group.
 *
 *     new Dropdown('Reports', new Property(Dropdown::Align, 'end'), new Item(…), new Item(…))
 */
final class Dropdown extends Group
{
    public const string Align = 'align';

    public function nodeType(): string
    {
        return 'dropdown';
    }

    public function isCollapsed(): bool
    {
        return true;
    }

    public function align(): string
    {
        return $this->string(self::Align) ?? 'start';
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(new PropertyDefinition(self::Align, Type::string(), default: 'start', allowedValues: ['start', 'end']));
    }
}
