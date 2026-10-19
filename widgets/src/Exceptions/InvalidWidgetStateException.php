<?php

declare(strict_types=1);

namespace Gcego\Widgets\Exceptions;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Untrusted widget state (date range, filter, page) that is not allowed. HTTP 400; names the parameter, never the value.
 */
final class InvalidWidgetStateException extends RuntimeException implements HttpExceptionInterface, WidgetException
{
    public static function invalid(string $parameter, string $reason): self
    {
        return new self(sprintf('The widget state "%s" %s', mb_substr($parameter, 0, 64), $reason));
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
