<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Exceptions;

use InvalidArgumentException;

/**
 * Thrown by a definition's make() when its value is structurally invalid.
 */
class InvalidDefinitionException extends InvalidArgumentException implements BlueprintException
{
    final public function __construct(
        public readonly string $key,
        public readonly string $reason,
        public readonly ?string $hint = null,
    ) {
        parent::__construct(sprintf('Invalid "%s" definition: %s', $key, $reason).($hint === null ? '' : ' '.$hint));
    }

    public static function because(string $key, string $reason, ?string $hint = null): static
    {
        return new static($key, $reason, $hint);
    }
}
