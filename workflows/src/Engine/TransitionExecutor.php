<?php

declare(strict_types=1);

namespace Gcego\Workflows\Engine;

use Gcego\Workflows\Access\WorkflowAuthorizer;
use Gcego\Workflows\Actors\SystemActor;
use Gcego\Workflows\Approvals\ApprovalCoordinator;
use Gcego\Workflows\Automation\AutomationDispatcher;
use Gcego\Workflows\Contracts\Condition;
use Gcego\Workflows\Contracts\TransitionGuard;
use Gcego\Workflows\Events\TransitionCompleted;
use Gcego\Workflows\Exceptions\InvalidTransitionException;
use Gcego\Workflows\Exceptions\WorkflowAuthorizationException;
use Gcego\Workflows\History\EventType;
use Gcego\Workflows\History\HistoryRecorder;
use Gcego\Workflows\Instances\InstanceStatus;
use Gcego\Workflows\Instances\WorkflowContext;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\Transitions\Transition;
use Gcego\Workflows\Workflow;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * Runs one transition on a locked instance, in order:
 *
 * 1. the instance is active (terminal states only via Reopen transitions);
 * 2. the transition leaves the current state (server state, never the browser's);
 * 3. the actor is authorized (WorkflowAuthorizer);
 * 4. a required approval is approved in this state visit;
 * 5. the comment rule holds;
 * 6. the guard allows it;
 * 7. a branch condition may choose the destination;
 * 8. history is appended and the destination entered.
 *
 * Refusals throw before anything is written to the instance.
 */
final readonly class TransitionExecutor
{
    public function __construct(
        private Container $container,
        private WorkflowAuthorizer $authorizer,
        private ApprovalCoordinator $approvals,
        private HistoryRecorder $history,
        private StateEntry $entry,
        private AutomationDispatcher $automation,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function apply(WorkflowInstance $instance, Workflow $workflow, string $name, Model|SystemActor $actor, ?string $comment, array $input, string $correlation, Effects $effects): TransitionResult
    {
        $transition = $this->check($instance, $workflow, $name, $actor, $comment, $input);
        $from = $instance->state;
        $context = new WorkflowContext($instance, $workflow, $actor, $transition, $comment, $input);
        $to = $transition->to();

        foreach ($transition->branches() as $branch) {
            $condition = $this->container->make($branch->condition());

            if ($condition instanceof Condition && $condition->passes($context)) {
                $to = $branch->to();

                break;
            }
        }

        $reopened = $instance->status === InstanceStatus::Completed;
        $event = $this->history->record($instance, $reopened ? EventType::Reopened : EventType::Transitioned, $actor, $correlation, $effects, from: $from, to: $to, metadata: [
            'transition' => $transition->nodeName(),
            'comment' => $transition->comment() === 'none' || $comment === null || trim($comment) === '' ? null : mb_substr(trim($comment), 0, 2000),
        ]);

        $this->entry->enter($instance, $workflow, $to, $event->sequence, $actor, $correlation, $effects);

        foreach ($transition->automations() as $automation) {
            $this->automation->schedule($instance, $automation, 'transition:'.$transition->nodeName(), $correlation, $effects);
        }

        $result = new TransitionResult($instance->ulid, $transition->nodeName(), $from, $to, $instance->status->value, $instance->lock_version + 1, $correlation);
        $effects->event(new TransitionCompleted($instance->workflow, $instance->tenant, $result, $actor instanceof SystemActor ? 'system' : 'user'));

        return $result;
    }

    /**
     * Steps 1–6 without writing anything (also used to list available transitions).
     *
     * @param  array<string, mixed>  $input
     *
     * @throws InvalidTransitionException|WorkflowAuthorizationException
     */
    public function check(WorkflowInstance $instance, Workflow $workflow, string $name, Model|SystemActor $actor, ?string $comment = null, array $input = [], bool $guards = true, bool $comments = true): Transition
    {
        if ($instance->status === InstanceStatus::Suspended) {
            throw InvalidTransitionException::because('suspended');
        }

        if ($instance->status === InstanceStatus::Cancelled) {
            throw InvalidTransitionException::because('finished');
        }

        $transition = $workflow->transition($name, $instance->state);

        if ($transition === null || ($instance->status === InstanceStatus::Completed && ! $transition->reopens())) {
            throw InvalidTransitionException::because($instance->status === InstanceStatus::Completed ? 'finished' : 'state', ['transition' => $name, 'state' => $instance->state]);
        }

        $denial = $this->authorizer->transitionDenial($workflow, $transition, $instance, $instance->subject, $actor);

        if ($denial !== null) {
            throw WorkflowAuthorizationException::for('run this transition');
        }

        $required = $transition->requiredApproval();

        if ($required !== null && $this->approvals->outcome($instance, $required) !== 'approved') {
            throw InvalidTransitionException::because('approval', ['approval' => $workflow->approval($required)?->label() ?? $required]);
        }

        if ($comments && $transition->comment() === 'required' && ($comment === null || trim($comment) === '')) {
            throw InvalidTransitionException::because('comment');
        }

        if ($guards && $transition->guard() !== null) {
            $guard = $this->container->make($transition->guard());
            $result = $guard instanceof TransitionGuard ? $guard->check(new WorkflowContext($instance, $workflow, $actor, $transition, $comment, $input)) : false;

            if ($result === false || ($result instanceof GuardResult && ! $result->allowed)) {
                throw new InvalidTransitionException('guard', $result instanceof GuardResult && $result->reason !== null ? $result->reason : (string) __('gcego-workflows::messages.errors.guard'));
            }
        }

        return $transition;
    }
}
