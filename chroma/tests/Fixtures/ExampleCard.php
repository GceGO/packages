<?php

declare(strict_types=1);

namespace Gcego\Chroma\Tests\Fixtures;

use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Contracts\BlueprintPart;
use Gcego\Components\Component;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;

/**
 * A card component as a future layouts/widgets package might ship it.
 */
final class ExampleCard extends Component implements BlueprintPart
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('fixtures.card')
            ->withProperties(new PropertyDefinition(self::Label, Type::string(), required: true));
    }

    protected static function shorthand(): array
    {
        return [self::Label];
    }

    public function blueprintRole(): BlueprintRole
    {
        return BlueprintRole::multiple('card');
    }
}
