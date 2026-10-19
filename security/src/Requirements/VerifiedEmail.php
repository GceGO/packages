<?php

declare(strict_types=1);

namespace Gcego\Security\Requirements;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\AuthorizationEngine;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Contracts\Requirement;
use Illuminate\Contracts\Auth\MustVerifyEmail;

/**
 * Requires a verified email address, with the same rule as Laravel's
 * "verified" middleware (and gcego/sentinel): users that do not implement
 * MustVerifyEmail pass. Guests are denied.
 */
final readonly class VerifiedEmail implements Requirement
{
    public function evaluate(AuthorizationEngine $engine, AuthorizationContext $context, mixed $resource = null): AuthorizationDecision
    {
        return match (true) {
            $context->user === null => AuthorizationDecision::deny('verified', DenialReason::Unauthenticated, $resource, $engine->strategy()),
            $context->user instanceof MustVerifyEmail && ! $context->user->hasVerifiedEmail() => AuthorizationDecision::deny('verified', DenialReason::EmailNotVerified, $resource, $engine->strategy()),
            default => AuthorizationDecision::allow('verified', $resource),
        };
    }

    public function describe(): string
    {
        return 'verified email';
    }

    /**
     * @return array{verified: true}
     */
    public function normalize(): array
    {
        return ['verified' => true];
    }
}
