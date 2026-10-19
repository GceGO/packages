<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Tests\Fixtures;

use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Contracts\BlueprintPart;
use Gcego\Blueprints\Contracts\PartContainer;
use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;

/**
 * A layout section that groups other parts (PartContainer).
 */
final class ExampleSection extends Component implements PartContainer
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('fixtures.ExampleSection')
            ->withProperties(new PropertyDefinition(self::Name, Type::string(), required: true))
            ->withAllowedChildren([BlueprintPart::class]);
    }

    protected static function shorthand(): array
    {
        return [self::Name];
    }

    public function blueprintRole(): BlueprintRole
    {
        return BlueprintRole::multiple(BlueprintRole::Layout);
    }
}
