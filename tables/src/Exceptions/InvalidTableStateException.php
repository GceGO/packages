<?php

declare(strict_types=1);

namespace Gcego\Tables\Exceptions;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Untrusted table state (from the browser, a URL, stored preferences or a
 * saved view) that names an unknown column, filter, sort or value. HTTP 400;
 * the message names the parameter, never the value.
 */
final class InvalidTableStateException extends RuntimeException implements HttpExceptionInterface, TableException
{
    public static function invalid(string $parameter, string $reason): self
    {
        return new self(sprintf('The table state "%s" %s', mb_substr($parameter, 0, 64), $reason));
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
