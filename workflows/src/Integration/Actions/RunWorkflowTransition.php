<?php

declare(strict_types=1);

namespace Gcego\Workflows\Integration\Actions;

use Gcego\Actions\Contracts\ActionHandler;
use Gcego\Actions\Execution\ActionContext;
use Gcego\Workflows\Engine\TransitionResult;
use Gcego\Workflows\Engine\WorkflowEngine;
use Gcego\Workflows\Exceptions\InvalidTransitionException;
use Gcego\Workflows\Exceptions\WorkflowNotFoundException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * A gcego/actions handler running a transition of the record's running
 * instance as the acting user (the action's own authorization runs
 * first; the workflow's runs again inside the engine):
 *
 *     final class SubmitRegistration extends RunWorkflowTransition
 *     {
 *         protected function workflow(): string { return 'institution-registration'; }
 *         protected function transition(): string { return 'submit'; }
 *     }
 *
 *     new Action('submit', 'Submit', new Handler(SubmitRegistration::class), new Record(true))
 *
 * A validated "comment" input value is passed as the transition comment.
 * Refusals become validation errors on "workflow" (shown by the action UI).
 */
abstract class RunWorkflowTransition implements ActionHandler
{
    abstract protected function workflow(): string;

    abstract protected function transition(): string;

    public function handle(ActionContext $context): TransitionResult
    {
        $engine = app(WorkflowEngine::class);
        $user = $context->user();
        $record = $context->record;

        if (! $user instanceof Model || ! $record instanceof Model) {
            throw WorkflowNotFoundException::make('workflow instance');
        }

        $instance = $engine->running($this->workflow(), $record) ?? throw ValidationException::withMessages(['workflow' => (string) __('gcego-workflows::messages.errors.not_running')]);
        $comment = $context->input->values['comment'] ?? null;

        try {
            return $engine->transition($instance, $this->transition(), $user, is_string($comment) ? $comment : null);
        } catch (InvalidTransitionException $exception) {
            throw ValidationException::withMessages(['workflow' => $exception->getMessage()]);
        }
    }
}
