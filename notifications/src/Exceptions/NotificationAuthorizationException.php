<?php

declare(strict_types=1);

namespace Gcego\Notifications\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The current user may not use the notifications UI (no signed-in user, or another guard's user).
 */
final class NotificationAuthorizationException extends AccessDeniedHttpException implements NotificationException
{
    public static function noViewer(): self
    {
        return new self('Sign in to see your notifications.');
    }
}
