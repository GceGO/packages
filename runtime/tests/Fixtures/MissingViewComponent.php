<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;

/**
 * A component whose renderer points at a missing view.
 */
final class MissingViewComponent extends Component
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('fixtures.missing-view')->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Name];
    }
}
