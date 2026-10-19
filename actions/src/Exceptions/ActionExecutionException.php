<?php

declare(strict_types=1);

namespace Gcego\Actions\Exceptions;

use Gcego\Actions\Execution\ActionResult;
use RuntimeException;

/**
 * Thrown by ActionManager::executeOrFail() for any non-successful result.
 */
final class ActionExecutionException extends RuntimeException implements ActionException
{
    private ?ActionResult $result = null;

    public static function fromResult(ActionResult $result): self
    {
        $exception = new self(sprintf('Action %s ended with "%s": %s', $result->action, $result->status->value, $result->message));
        $exception->result = $result;

        return $exception;
    }

    public function result(): ?ActionResult
    {
        return $this->result;
    }
}
