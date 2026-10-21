<?php

declare(strict_types=1);

namespace Gcego\Components\Navigation;

use Gcego\Components\Foundation\SchemaComponent;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

class Pagination extends SchemaComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('navigation.pagination')
            ->withProperties(
                new PropertyDefinition('page', Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value > 0, ruleDescription: 'positive integer'),
                new PropertyDefinition('pageSize', Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value > 0, ruleDescription: 'positive integer'),
                new PropertyDefinition('total', Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 0, ruleDescription: 'non-negative integer'),
            )
            ->withoutChildren();
    }
}
