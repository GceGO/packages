<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Tests\Fixtures;

use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Contracts\BlueprintPart;
use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;

/**
 * A part with a role no GCEGO package knows about.
 */
final class ReportChart extends Component implements BlueprintPart
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('fixtures.ReportChart')
            ->withProperties(new PropertyDefinition(self::Name, Type::string(), required: true))
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Name];
    }

    public function blueprintRole(): BlueprintRole
    {
        return BlueprintRole::multiple('reports.chart');
    }
}
