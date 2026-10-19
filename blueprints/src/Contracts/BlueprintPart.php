<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Contracts;

use Gcego\Blueprints\BlueprintRole;
use Gcego\Components\Contracts\ComponentInterface;

/**
 * A component that can be placed directly in a Blueprint.
 *
 * Future packages implement it on their top-level components (a table, a
 * form, an action). Classification is by this contract only: no namespace
 * or naming heuristics, and no reflection.
 */
interface BlueprintPart extends ComponentInterface
{
    public function blueprintRole(): BlueprintRole;
}
