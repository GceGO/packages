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
 * A future gcego/tables Table: columns and row actions are its private content.
 */
final class ExampleTable extends Component implements BlueprintPart
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('fixtures.ExampleTable')
            ->withProperties(new PropertyDefinition(self::Name, Type::string(), required: true))
            ->withAllowedChildren([ExampleColumn::class, ExampleAction::class]);
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }

    public function blueprintRole(): BlueprintRole
    {
        return BlueprintRole::single(BlueprintRole::Table);
    }
}
