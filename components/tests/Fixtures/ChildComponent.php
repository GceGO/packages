<?php

declare(strict_types=1);

namespace Gcego\Components\Tests\Fixtures;

use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;

final class ChildComponent extends Component
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('fixtures.child')
            ->withProperties(new PropertyDefinition(self::Name, Type::string(), required: true))
            ->withAllowedChildren([ChildComponent::class]);
    }

    protected static function shorthand(): array
    {
        return [self::Name];
    }
}
