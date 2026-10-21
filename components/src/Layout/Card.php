<?php

declare(strict_types=1);

namespace Gcego\Components\Layout;

use Gcego\Components\Concerns\HasColumns;
use Gcego\Components\Concerns\HasStatePath;
use Gcego\Components\Schema\ComponentDefinition;

class Card extends TitledBlock
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('layout.card')
            ->withConcerns(HasColumns::class, HasStatePath::class);
    }
}
