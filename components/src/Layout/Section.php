<?php

declare(strict_types=1);

namespace Gcego\Components\Layout;

use Gcego\Components\Concerns\HasColumns;
use Gcego\Components\Concerns\HasStatePath;
use Gcego\Components\Foundation\SchemaComponent;
use Gcego\Components\Schema\ComponentDefinition;

class Section extends SchemaComponent
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('section')
            ->withConcerns(HasColumns::class, HasStatePath::class);
    }
}
