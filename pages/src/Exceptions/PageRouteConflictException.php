<?php

declare(strict_types=1);

namespace Gcego\Pages\Exceptions;

use LogicException;

/**
 * Two routes would answer the same name or URL. Raised when page routes are
 * registered, so the conflict surfaces at boot (or route:cache), not per request.
 */
final class PageRouteConflictException extends LogicException implements PageException
{
    public static function name(string $name, string $page, string $existing): self
    {
        return new self(sprintf('Page "%s" cannot register route name "%s": it is already used by %s.', $page, $name, $existing));
    }

    public static function uri(string $uri, string $page, string $existing): self
    {
        return new self(sprintf('Page "%s" cannot register GET "%s": the same URL is already answered by %s.', $page, $uri, $existing));
    }
}
