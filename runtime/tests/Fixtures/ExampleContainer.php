<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Components\Component;
use Gcego\Components\Schema\ComponentDefinition;

/**
 * A container rendering its children as a list.
 */
final class ExampleContainer extends Component
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('fixtures.container');
    }

    protected static function shorthand(): array
    {
        return [self::Name];
    }
}
