<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Components\Component;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

/**
 * A paragraph of plain text.
 */
final class ExampleParagraph extends Component
{
    public const string Text = 'text';

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('fixtures.paragraph')
            ->withProperties(new PropertyDefinition(self::Text, Type::string(), required: true))
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Text];
    }
}
