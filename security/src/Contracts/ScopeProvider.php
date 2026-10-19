<?php

declare(strict_types=1);

namespace Gcego\Security\Contracts;

use Gcego\Data\Contracts\QueryConstraint;
use Gcego\Data\Definition\SourceDefinition;
use Gcego\Security\Authorization\AuthorizationContext;

/**
 * Mandatory, context-dependent data constraints (tenant, user, role or
 * membership scopes) for gcego/data sources. Applied to every listing and
 * record lookup, before request filters, and impossible to remove from
 * request input.
 */
interface ScopeProvider
{
    /**
     * @return list<QueryConstraint> Constraints for this source in this context; [] when none apply.
     */
    public function constraints(SourceDefinition $source, AuthorizationContext $context): array;
}
