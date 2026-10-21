<?php

declare(strict_types=1);

namespace Gcego\Components\Feedback;

use Gcego\Components\Foundation\SchemaComponent;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

class Spinner extends SchemaComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('feedback.spinner')
            ->withProperties(
                new PropertyDefinition('size', Type::string()),
                new PropertyDefinition('color', Type::string()),
            )
            ->withoutChildren();
    }
}
