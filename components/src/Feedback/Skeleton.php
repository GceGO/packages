<?php

declare(strict_types=1);

namespace Gcego\Components\Feedback;

use Gcego\Components\Foundation\SchemaComponent;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

class Skeleton extends SchemaComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('feedback.skeleton')
            ->withProperties(
                new PropertyDefinition('rows', Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 0, ruleDescription: 'non-negative integer'),
                new PropertyDefinition('width', Type::string()),
                new PropertyDefinition('height', Type::string()),
            )
            ->withoutChildren();
    }
}
