<?php

declare(strict_types=1);

namespace Gcego\Media\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The user may not do this with this media (403).
 */
final class MediaAccessDeniedException extends AccessDeniedHttpException implements MediaException
{
    public static function for(string $ability): self
    {
        return new self(sprintf('You may not %s this file.', $ability));
    }
}
