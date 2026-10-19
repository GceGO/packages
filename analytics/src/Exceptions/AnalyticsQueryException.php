<?php

declare(strict_types=1);

namespace Gcego\Analytics\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * A query was rejected before execution (an unknown field, a disallowed
 * aggregate or operator, a limit exceeded). $reason is a stable code; the
 * message names the offending input, never data (422).
 */
final class AnalyticsQueryException extends UnprocessableEntityHttpException implements AnalyticsException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function rejected(string $reason, string $message): self
    {
        return new self($reason, $message);
    }
}
