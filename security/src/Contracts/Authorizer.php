<?php

declare(strict_types=1);

namespace Gcego\Security\Contracts;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;

/**
 * A custom authorization provider, consulted before Laravel's Gate for
 * abilities it knows. Registered explicitly (SecurityManager::extend()), so
 * no grant is ever hidden.
 */
interface Authorizer
{
    /**
     * @return AuthorizationDecision|null Null to let the next authorizer (and finally the Gate) decide.
     */
    public function decide(string $ability, mixed $resource, AuthorizationContext $context): ?AuthorizationDecision;
}
