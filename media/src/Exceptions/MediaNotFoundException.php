<?php

declare(strict_types=1);

namespace Gcego\Media\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * No media with this id exists for the viewer (404: other tenants' media looks missing).
 */
final class MediaNotFoundException extends NotFoundHttpException implements MediaException
{
    public static function make(): self
    {
        return new self('File not found.');
    }
}
