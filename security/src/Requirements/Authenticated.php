<?php

declare(strict_types=1);

namespace Gcego\Security\Requirements;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\AuthorizationEngine;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Contracts\Requirement;

/**
 * Requires an authenticated user, optionally on a specific guard:
 *
 *     new Authenticated
 *     new Authenticated('admin')
 */
final readonly class Authenticated implements Requirement
{
    public function __construct(public ?string $guard = null) {}

    public function evaluate(AuthorizationEngine $engine, AuthorizationContext $context, mixed $resource = null): AuthorizationDecision
    {
        return match (true) {
            $context->user === null => AuthorizationDecision::deny('authenticated', DenialReason::Unauthenticated, $resource, $engine->strategy()),
            $this->guard !== null && $context->guard !== $this->guard => AuthorizationDecision::deny('authenticated', DenialReason::WrongGuard, $resource, $engine->strategy(), metadata: ['guard' => $this->guard]),
            default => AuthorizationDecision::allow('authenticated', $resource),
        };
    }

    public function describe(): string
    {
        return 'authenticated'.($this->guard === null ? '' : ' on guard '.$this->guard);
    }

    /**
     * @return array{guard: string|null}
     */
    public function normalize(): array
    {
        return ['guard' => $this->guard];
    }
}
