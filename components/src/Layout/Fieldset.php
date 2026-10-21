<?php

declare(strict_types=1);

namespace Gcego\Components\Layout;

use Gcego\Components\Concerns\HasColumns;
use Gcego\Components\Concerns\HasStatePath;
use Gcego\Components\Schema\ComponentDefinition;

class Fieldset extends TitledBlock
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('layout.fieldset')
            ->withConcerns(HasColumns::class, HasStatePath::class);
    }
}
