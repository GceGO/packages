<?php

declare(strict_types=1);

namespace Gcego\Media\Exceptions;

use RuntimeException;

/**
 * Metadata extraction, a transformation or a scan failed.
 */
final class MediaProcessingException extends RuntimeException implements MediaException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
