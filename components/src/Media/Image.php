<?php

declare(strict_types=1);

namespace Gcego\Components\Media;

use Gcego\Components\Schema\ComponentDefinition;

class Image extends ImageComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('media.image');
    }
}
