<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;

/**
 * A component nobody registered a renderer for.
 */
final class UnrenderedComponent extends Component
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('fixtures.unrendered')->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Name];
    }
}
