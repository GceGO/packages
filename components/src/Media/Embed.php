<?php

declare(strict_types=1);

namespace Gcego\Components\Media;

use Gcego\Components\Foundation\SchemaComponent;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

class Embed extends SchemaComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('media.embed')
            ->withProperties(
                new PropertyDefinition('src', Type::string()),
                new PropertyDefinition('title', Type::string()),
                new PropertyDefinition('width', Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value > 0, ruleDescription: 'positive integer'),
                new PropertyDefinition('height', Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value > 0, ruleDescription: 'positive integer'),
            )
            ->withoutChildren();
    }
}
