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
 * A future gcego/data Source: binds a model, at most one per blueprint.
 */
final class ExampleSource extends Component implements BlueprintPart
{
    public const string Model = 'model';

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('fixtures.ExampleSource')
            ->withProperties(new PropertyDefinition(self::Model, Type::classString(), required: true))
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Model, self::Name];
    }

    public function blueprintRole(): BlueprintRole
    {
        return BlueprintRole::single(BlueprintRole::Source);
    }
}
