<?php

declare(strict_types=1);

namespace Gcego\Components\DataDisplay;

use Gcego\Components\Foundation\SchemaComponent;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

class Table extends SchemaComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('dataDisplay.table')
            ->withProperties(
                new PropertyDefinition('data', Type::array()),
                new PropertyDefinition('pageSize', Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value > 0, ruleDescription: 'positive integer'),
                new PropertyDefinition('sortable', Type::bool()),
                new PropertyDefinition('searchable', Type::bool()),
                new PropertyDefinition('selectable', Type::bool()),
            );
    }
}
