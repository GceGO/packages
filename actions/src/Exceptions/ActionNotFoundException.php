<?php

declare(strict_types=1);

namespace Gcego\Actions\Exceptions;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * No action with that name belongs to the trusted definition (404). Names
 * from requests never reach anything outside the definition they target.
 */
final class ActionNotFoundException extends RuntimeException implements ActionException, HttpExceptionInterface
{
    public static function in(string $definition): self
    {
        return new self(sprintf('The requested action does not exist in %s.', $definition));
    }

    public function getStatusCode(): int
    {
        return 404;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }
}
