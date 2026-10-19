<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;

/**
 * A heading primitive (stand-in for a future layouts package).
 */
final class ExampleHeading extends Component
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('fixtures.heading')
            ->withProperties(new PropertyDefinition(self::Label, Type::string(), required: true))
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Label];
    }
}
