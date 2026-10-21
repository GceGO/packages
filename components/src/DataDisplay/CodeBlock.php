<?php

declare(strict_types=1);

namespace Gcego\Components\DataDisplay;

use Gcego\Components\Foundation\SchemaComponent;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

class CodeBlock extends SchemaComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('dataDisplay.codeBlock')
            ->withProperties(
                new PropertyDefinition('content', Type::string()),
                new PropertyDefinition('language', Type::string()),
            )
            ->withoutChildren();
    }
}
