<?php

declare(strict_types=1);

namespace Gcego\Security\Exceptions;

use InvalidArgumentException;

/**
 * A developer-declared permission, ability or field rule is malformed.
 */
final class InvalidPermissionException extends InvalidArgumentException implements SecurityException
{
    public static function invalidName(string $kind, string $name): self
    {
        return new self(sprintf('"%s" is not a valid %s. Use letters, digits, "-", "_", ":" and "*", separated by ".".', mb_substr($name, 0, 80), $kind));
    }

    public static function invalid(string $node, string $reason): self
    {
        return new self(sprintf('%s is invalid: %s', $node, $reason));
    }
}
