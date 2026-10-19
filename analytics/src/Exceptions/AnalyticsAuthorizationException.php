<?php

declare(strict_types=1);

namespace Gcego\Analytics\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The viewer may not see or do this (403).
 */
final class AnalyticsAuthorizationException extends AccessDeniedHttpException implements AnalyticsException
{
    public static function for(string $what): self
    {
        return new self(sprintf('You may not %s.', $what));
    }
}
