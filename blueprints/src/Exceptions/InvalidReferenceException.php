<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Exceptions;

use Gcego\Blueprints\Validation\BlueprintValidationResult;
use UnexpectedValueException;

/**
 * Thrown by the ReferenceResolver when an engine asks for a reference that
 * fails integration validation, so the class is never executed.
 */
class InvalidReferenceException extends UnexpectedValueException implements BlueprintException
{
    public static function forKey(string $blueprint, string $key, BlueprintValidationResult $result): self
    {
        return new self(sprintf('Blueprint "%s" has an invalid "%s" reference:'."\n%s", $blueprint, $key, $result->format()));
    }

    public static function contract(string $blueprint, string $key, string $class, string $contract): self
    {
        return new self(sprintf('Blueprint "%s": the "%s" reference %s must implement %s.', $blueprint, $key, $class, $contract));
    }
}
