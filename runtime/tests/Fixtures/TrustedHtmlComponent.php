<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;

/**
 * A component whose renderer emits trusted markup.
 */
final class TrustedHtmlComponent extends Component
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('fixtures.trusted')->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Name];
    }
}
