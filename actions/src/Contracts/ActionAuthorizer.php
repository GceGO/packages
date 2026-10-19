<?php

declare(strict_types=1);

namespace Gcego\Actions\Contracts;

use Gcego\Actions\Resolution\ResolvedAction;
use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;

/**
 * Decides whether an action may run, for its record if any. Bound to
 * SecurityActionAuthorizer (gcego/security). Implementations must fail closed.
 */
interface ActionAuthorizer
{
    public function authorize(ResolvedAction $action, AuthorizationContext $context, mixed $record = null): AuthorizationDecision;
}
