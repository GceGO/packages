<?php

declare(strict_types=1);

namespace Gcego\Security\Requirements;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Authorization\AuthorizationDecision;
use Gcego\Security\Authorization\AuthorizationEngine;
use Gcego\Security\Authorization\DenialReason;
use Gcego\Security\Contracts\Requirement;

/**
 * Requires a recent password confirmation, read from the same session value
 * Laravel's "password.confirm" middleware and gcego/sentinel write
 * (auth.password_confirmed_at). It never confirms anything itself; send the
 * user through the application's confirmation flow when denied.
 */
final readonly class PasswordConfirmed implements Requirement
{
    /**
     * @param  int  $seconds  How recent the confirmation must be (Laravel's default auth.password_timeout is 10800).
     */
    public function __construct(public int $seconds = 10800) {}

    public function evaluate(AuthorizationEngine $engine, AuthorizationContext $context, mixed $resource = null): AuthorizationDecision
    {
        $confirmedAt = $engine->session()?->get('auth.password_confirmed_at');

        return match (true) {
            $context->user === null => AuthorizationDecision::deny('password.confirmed', DenialReason::Unauthenticated, $resource, $engine->strategy()),
            is_numeric($confirmedAt) && time() - (int) $confirmedAt < $this->seconds => AuthorizationDecision::allow('password.confirmed', $resource),
            default => AuthorizationDecision::deny('password.confirmed', DenialReason::PasswordNotConfirmed, $resource, $engine->strategy()),
        };
    }

    public function describe(): string
    {
        return sprintf('password confirmed within %d seconds', $this->seconds);
    }

    /**
     * @return array{seconds: int}
     */
    public function normalize(): array
    {
        return ['seconds' => $this->seconds];
    }
}
