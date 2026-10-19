<?php

declare(strict_types=1);

namespace Gcego\Workflows\Livewire\Concerns;

use Gcego\Workflows\Exceptions\InvalidTransitionException;
use Gcego\Workflows\Exceptions\WorkflowAuthorizationException;
use Gcego\Workflows\Exceptions\WorkflowConflictException;
use Gcego\Workflows\Exceptions\WorkflowException;
use Gcego\Workflows\Exceptions\WorkflowNotFoundException;
use Illuminate\Database\Eloquent\Model;

/**
 * The signed-in user (never an identity from the browser), and refusals
 * turned into accessible messages.
 */
trait ResolvesViewer
{
    protected function viewer(): Model
    {
        $user = auth()->user();

        if (! $user instanceof Model) {
            abort(403);
        }

        return $user;
    }

    protected function refusal(WorkflowException $exception): string
    {
        return match (true) {
            $exception instanceof InvalidTransitionException => $exception->getMessage(),
            $exception instanceof WorkflowConflictException => (string) __('gcego-workflows::messages.errors.stale'),
            $exception instanceof WorkflowAuthorizationException => (string) __('gcego-workflows::messages.errors.forbidden'),
            $exception instanceof WorkflowNotFoundException => (string) __('gcego-workflows::messages.errors.missing'),
            default => (string) __('gcego-workflows::messages.errors.failed'),
        };
    }

    protected static function cleanComment(mixed $comment): ?string
    {
        return is_string($comment) && trim($comment) !== '' ? mb_substr(trim($comment), 0, 2000) : null;
    }
}
