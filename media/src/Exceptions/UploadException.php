<?php

declare(strict_types=1);

namespace Gcego\Media\Exceptions;

use RuntimeException;

/**
 * A file was refused or could not be stored. $errors are user-facing
 * messages (no paths, no internals); $code is a stable reason.
 */
final class UploadException extends RuntimeException implements MediaException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(public readonly string $reason, public readonly array $errors)
    {
        parent::__construct($errors[0] ?? 'The file was refused.');
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    public static function refused(string $reason, array $replace = []): self
    {
        return new self($reason, [(string) __('gcego-media::messages.errors.'.$reason, $replace)]);
    }
}
