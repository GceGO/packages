<?php

declare(strict_types=1);

namespace Gcego\Navigation\Components;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Navigation\NavigationNode;

/**
 * A non-clickable label before the nodes that follow it. A heading with no
 * visible node after it (before the next heading or divider) is not shown.
 */
final class Heading extends NavigationNode
{
    public function nodeType(): string
    {
        return 'heading';
    }

    protected static function shorthand(): array
    {
        return [self::Label];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withProperties(new PropertyDefinition(self::Label, Type::string(), required: true))
            ->withoutChildren();
    }
}
