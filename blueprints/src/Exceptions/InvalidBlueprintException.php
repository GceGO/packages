<?php

declare(strict_types=1);

namespace Gcego\Blueprints\Exceptions;

use DomainException;
use Gcego\Blueprints\Validation\BlueprintValidationResult;

/**
 * Thrown by strict blueprint validation, or when a blueprint without a valid name is used where identity is required.
 */
class InvalidBlueprintException extends DomainException implements BlueprintException
{
    private const int MaxErrorsInMessage = 10;

    private ?BlueprintValidationResult $result = null;

    public static function fromResult(BlueprintValidationResult $result): self
    {
        $errors = $result->errors();
        $message = sprintf('Blueprint "%s" failed validation with %d error(s).', $result->blueprint, count($errors));

        foreach (array_slice($errors, 0, self::MaxErrorsInMessage) as $error) {
            $message .= "\n\n".$error->format();
        }

        if (count($errors) > self::MaxErrorsInMessage) {
            $message .= sprintf("\n\n... and %d more error(s).", count($errors) - self::MaxErrorsInMessage);
        }

        $exception = new self($message);
        $exception->result = $result;

        return $exception;
    }

    public static function unnamed(string $class): self
    {
        return new self(sprintf(
            '%s has no valid name. Declare one with new Property(Blueprint::Name, \'subjects\') or the shorthand new Blueprint(\'subjects\', ...).',
            $class,
        ));
    }

    public function result(): ?BlueprintValidationResult
    {
        return $this->result;
    }
}
