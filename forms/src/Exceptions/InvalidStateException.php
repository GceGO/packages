<?php

declare(strict_types=1);

namespace Gcego\Forms\Exceptions;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Submitted state addressed a path the trusted schema does not declare (400).
 * Messages name paths, never values.
 */
final class InvalidStateException extends RuntimeException implements FormException, HttpExceptionInterface
{
    public static function unknownPath(string $path): self
    {
        return new self(sprintf('"%s" is not a field of this form.', mb_substr((string) preg_replace('/[^\x20-\x7E]/', '?', $path), 0, 80)));
    }

    public function getStatusCode(): int
    {
        return 400;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }
}
