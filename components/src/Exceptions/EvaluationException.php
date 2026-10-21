<?php

declare(strict_types=1);

namespace Gcego\Components\Exceptions;

use Gcego\Components\Support\ValueDescriber;
use RuntimeException;

/**
 * Thrown when a computed property value cannot be evaluated, or evaluates
 * to a value its property definition does not accept.
 */
class EvaluationException extends RuntimeException implements ComponentException
{
    /**
     * @param  list<string>  $available
     */
    public static function unresolvableParameter(string $subject, string $parameter, array $available, ?string $suggestion, bool $needsState = false): self
    {
        $lines = [
            sprintf('Cannot inject parameter $%s into the closure for %s.', $parameter, $subject),
            '',
            'Available: '.($available === [] ? 'nothing' : '$'.implode(', $', $available)),
        ];

        if ($needsState) {
            $lines[] = '';
            $lines[] = 'Supply state with EvaluationContext::withState() to inject $get, $set and $state.';
        } elseif ($suggestion !== null) {
            $lines[] = '';
            $lines[] = sprintf('Did you mean $%s?', $suggestion);
        }

        return new self(implode("\n", $lines));
    }

    public static function invalidResult(string $subject, string $expected, mixed $result): self
    {
        return new self(implode("\n", [
            sprintf('The closure for %s returned an invalid value.', $subject),
            '',
            'Expected: '.$expected,
            'Received: '.ValueDescriber::describe($result),
        ]));
    }
}
