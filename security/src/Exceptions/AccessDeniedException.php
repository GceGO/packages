<?php

declare(strict_types=1);

namespace Gcego\Security\Exceptions;

use Gcego\Security\Authorization\AuthorizationDecision;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * A denied AuthorizationDecision, thrown. It extends Laravel's
 * AuthorizationException, so Laravel renders it with the decision's status
 * (403, or 404 when the decision conceals the resource) and existing
 * handlers, Livewire and tests treat it as an authorization failure.
 *
 * The message is the decision's public message; diagnostic details stay in decision().
 */
class AccessDeniedException extends AuthorizationException implements SecurityException
{
    private ?AuthorizationDecision $decision = null;

    public static function fromDecision(AuthorizationDecision $decision): static
    {
        $exception = new static($decision->message()); // @phpstan-ignore new.static
        $exception->decision = $decision;
        $exception->withStatus($decision->status);

        return $exception;
    }

    public function decision(): ?AuthorizationDecision
    {
        return $this->decision;
    }
}
