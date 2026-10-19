<?php

declare(strict_types=1);

namespace Gcego\Widgets\Tests\Fixtures;

use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Contracts\PartContainer;
use Gcego\Components\Component;

/**
 * A minimal layout-like part container that embeds widgets in a blueprint.
 */
final class Panel extends Component implements PartContainer
{
    public function blueprintRole(): BlueprintRole
    {
        return BlueprintRole::multiple(BlueprintRole::Layout);
    }
}
