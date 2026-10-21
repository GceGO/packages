<?php

declare(strict_types=1);

namespace Gcego\Components\Exceptions;

use DomainException;
use Gcego\Components\Component;
use Gcego\Components\Validation\ValidationResult;

/**
 * Thrown by strict validation when a component tree violates its schemas.
 *
 * The full structured result remains available through result().
 */
class InvalidComponentException extends DomainException implements ComponentException
{
    private const int MaxErrorsInMessage = 10;

    private ValidationResult $result;

    public static function fromResult(ValidationResult $result): self
    {
        $errors = $result->errors();
        $shown = array_slice($errors, 0, self::MaxErrorsInMessage);

        $message = sprintf('Component validation failed with %d error(s).', count($errors));

        foreach ($shown as $error) {
            $message .= "\n\n".$error->format();
        }

        if (count($errors) > count($shown)) {
            $message .= sprintf("\n\n... and %d more error(s).", count($errors) - count($shown));
        }

        $exception = new self($message);
        $exception->result = $result;

        return $exception;
    }

    public function result(): ValidationResult
    {
        return $this->result;
    }
}
