<?php

declare(strict_types=1);

namespace Gcego\Modules\Exceptions;

use InvalidArgumentException;

/**
 * A module, resource, relation, extension or modules configuration value is invalid.
 */
final class InvalidModuleException extends InvalidArgumentException implements ModuleException
{
    public static function invalid(string $subject, string $reason): self
    {
        return new self(sprintf('%s is invalid: %s', $subject, $reason));
    }

    /**
     * @param  list<string>  $problems
     */
    public static function problems(string $subject, array $problems): self
    {
        return new self(sprintf('%s is invalid: %s', $subject, implode(' ', $problems)));
    }
}
