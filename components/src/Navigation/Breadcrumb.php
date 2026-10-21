<?php

declare(strict_types=1);

namespace Gcego\Components\Navigation;

use Gcego\Components\Schema\ComponentDefinition;

class Breadcrumb extends NavigationItem
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('navigation.breadcrumb')
            ->withoutChildren();
    }
}
