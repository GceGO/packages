<?php

declare(strict_types=1);

namespace Gcego\Navigation\Exceptions;

/**
 * Two nodes of one navigation share a name (identities must be unique).
 */
final class DuplicateNavigationItemException extends InvalidNavigationException
{
    public static function forName(string $navigation, string $name, string $first, string $second): self
    {
        return new self(sprintf('Navigation "%s" has two nodes named "%s" (at "%s" and "%s"); give one a different Name.', $navigation, $name, $first, $second));
    }
}
