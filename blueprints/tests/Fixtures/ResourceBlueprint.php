<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Tests\Fixtures;

use Gcego\Blueprints\Blueprint;
use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Composition\CompositionPolicy;

/**
 * A stricter blueprint, as gcego/modules might define: a table is required,
 * only CRUD roles are allowed and there is at most one source.
 */
final class ResourceBlueprint extends Blueprint
{
    protected static function definePolicy(): CompositionPolicy
    {
        return new CompositionPolicy(
            requiredRoles: [BlueprintRole::Table],
            allowedRoles: [BlueprintRole::Source, BlueprintRole::Table, BlueprintRole::Form, BlueprintRole::Action],
            singleRoles: [BlueprintRole::Source],
        );
    }
}
