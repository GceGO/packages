<?php

declare(strict_types=1);

namespace Gcego\Components\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a schema patch targets a component that does not exist, or an
 * operation the target cannot take.
 */
class PatchException extends InvalidArgumentException implements ComponentException
{
    public static function noMatch(string $selector, ?string $suggestion): self
    {
        return new self(sprintf('No component matches "%s".', $selector).($suggestion === null ? '' : sprintf("\n\nDid you mean \"%s\"?", $suggestion)));
    }

    public static function rootOperation(string $operation): self
    {
        return new self(sprintf('The schema root cannot be targeted by %s(); patch a component inside it.', $operation));
    }

    public static function notAComponent(string $selector, string $operation): self
    {
        return new self(sprintf('%s() on "%s" needs a GCEGO Component it can copy.', $operation, $selector));
    }

    public static function invalidModification(string $selector): self
    {
        return new self(sprintf('modify("%s") must return a component or a component builder.', $selector));
    }
}
