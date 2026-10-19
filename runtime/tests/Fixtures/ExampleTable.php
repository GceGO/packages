<?php

declare(strict_types=1);

namespace Gcego\Runtime\Tests\Fixtures;

use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Contracts\BlueprintPart;
use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;

/**
 * A blueprint part standing in for a future gcego/tables table.
 */
final class ExampleTable extends Component implements BlueprintPart
{
    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('fixtures.table')
            ->withAllowedChildren([ExampleParagraph::class]);
    }

    protected static function shorthand(): array
    {
        return [self::Name];
    }

    public function blueprintRole(): BlueprintRole
    {
        return BlueprintRole::single(BlueprintRole::Table);
    }
}
