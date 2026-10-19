<?php

declare(strict_types=1);

namespace Gcego\Security\Requirements;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\AuthorizationEngine;
use Gcego\Security\Contracts\Requirement;

/**
 * Explicitly allows everyone, guests included. Without it, a definition
 * with no requirement is denied: public access is never inferred.
 * Other requirements on the same path still apply.
 */
final readonly class PublicAccess implements Requirement
{
    public function evaluate(AuthorizationEngine $engine, AuthorizationContext $context, mixed $resource = null): AuthorizationDecision
    {
        return AuthorizationDecision::allow('public', $resource, ['public' => true]);
    }

    public function describe(): string
    {
        return 'public access';
    }

    /**
     * @return array{public: true}
     */
    public function normalize(): array
    {
        return ['public' => true];
    }
}
