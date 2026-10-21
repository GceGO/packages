<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Components\Component;
use Gcego\Components\Schema\ComponentDefinition;

/**
 * A component whose renderer throws.
 */
final class FailingComponent extends Component
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('fixtures.failing')->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Name];
    }
}
