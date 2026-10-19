<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Tests\Fixtures;

use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;

/**
 * A table column or form field: an ordinary component, not a blueprint part.
 */
final class ExampleColumn extends Component
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('fixtures.column')
            ->withProperties(new PropertyDefinition(self::Name, Type::string(), required: true))
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }
}
