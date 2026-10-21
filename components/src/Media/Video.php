<?php

declare(strict_types=1);

namespace Gcego\Components\Media;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

class Video extends PlayableMedia
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('media.video')
            ->withProperties(
                new PropertyDefinition('poster', Type::string()),
            );
    }
}
