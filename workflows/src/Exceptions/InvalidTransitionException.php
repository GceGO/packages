<?php

declare(strict_types=1);

namespace Gcego\Workflows\Exceptions;

use RuntimeException;

/**
 * A transition, approval or task command was refused. $reason is a stable
 * code ("guard", "state", "approval"...), the message is user-facing.
 */
final class InvalidTransitionException extends RuntimeException implements WorkflowException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    public static function because(string $reason, array $replace = []): self
    {
        return new self($reason, (string) __('gcego-workflows::messages.errors.'.$reason, $replace));
    }
}
