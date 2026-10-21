<?php

declare(strict_types=1);

namespace Gcego\Navigation\Components;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Navigation\NavigationNode;

/**
 * A decorative separator between nodes (hidden from screen readers).
 * Dividers at the start or end, or next to another divider, are not shown.
 */
final class Divider extends NavigationNode
{
    public function nodeType(): string
    {
        return 'divider';
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withoutChildren();
    }
}
