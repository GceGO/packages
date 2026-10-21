<?php

declare(strict_types=1);

namespace Gcego\Components\Tests\Fixtures;

use Gcego\Components\Layout\Section;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

final class DashboardSection extends Section
{
    public const string Collapsible = 'collapsible';

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withType('dashboard.section')->withProperties(
            new PropertyDefinition(self::Collapsible, Type::bool(), default: false),
        );
    }
}
