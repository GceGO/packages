<?php

declare(strict_types=1);

namespace Gcego\Components\Layout;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

class Column extends LayoutBlock
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('layout.column')
            ->withProperties(
                new PropertyDefinition('span', Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value > 0, ruleDescription: 'positive integer'),
            );
    }
}
