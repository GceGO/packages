<?php

declare(strict_types=1);

namespace Gcego\Components\Exceptions;

use Gcego\Components\Component;
use Gcego\Components\Support\ValueDescriber;
use InvalidArgumentException;

/**
 * Thrown when a component graph cannot be traversed safely: a component that
 * contains itself, or nesting deeper than the configured limit.
 */
class InvalidChildException extends InvalidArgumentException implements ComponentException
{
    public static function cycle(string $path, string $componentClass): self
    {
        return new self(sprintf(
            'Circular component reference detected at "%s": %s contains itself as a descendant.',
            $path,
            ValueDescriber::className($componentClass),
        ));
    }

    public static function nestingTooDeep(string $path, int $maxDepth): self
    {
        return new self(sprintf(
            'Component nesting exceeds the maximum depth of %d at "%s".',
            $maxDepth,
            $path,
        ));
    }
}
