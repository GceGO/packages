<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Exceptions;

use LogicException;

/**
 * Thrown when a package registers an invalid or conflicting definition type.
 */
class DefinitionRegistrationException extends LogicException implements BlueprintException
{
    public static function notADefinition(string $class): self
    {
        return new self(sprintf('%s cannot be registered: definition types must implement %s.', $class, 'Gcego\\Blueprints\\Contracts\\BlueprintDefinition'));
    }

    public static function invalidKey(string $class, string $key): self
    {
        return new self(sprintf('%s declares the invalid definition key "%s". Keys are lowercase identifiers such as "navigation" or "reports.charts".', $class, $key));
    }

    public static function tooLate(string $class): self
    {
        return new self(sprintf('%s cannot be registered as required: blueprints were already defined without it. Register required definition types from a service provider\'s register() method.', $class));
    }

    public static function keyTaken(string $key, string $existing, string $class): self
    {
        return new self(sprintf('The blueprint definition key "%s" already belongs to %s; %s cannot claim it.', $key, $existing, $class));
    }
}
