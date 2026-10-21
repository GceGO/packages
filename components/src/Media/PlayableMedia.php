<?php

declare(strict_types=1);

namespace Gcego\Components\Media;

use Gcego\Components\Foundation\SchemaComponent;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

/** Shared schema contract for the PlayableMedia family. */
abstract class PlayableMedia extends SchemaComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition('src', Type::string()),
            new PropertyDefinition('controls', Type::bool()),
            new PropertyDefinition('autoplay', Type::bool()),
            new PropertyDefinition('muted', Type::bool()),
            new PropertyDefinition('loop', Type::bool()),
        )->withoutChildren();
    }
}
