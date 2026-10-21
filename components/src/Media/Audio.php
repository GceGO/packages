<?php

declare(strict_types=1);

namespace Gcego\Components\Media;

use Gcego\Components\Schema\ComponentDefinition;

class Audio extends PlayableMedia
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('media.audio');
    }
}
