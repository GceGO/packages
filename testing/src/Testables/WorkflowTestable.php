<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Closure;
use Gcego\Testing\Assertions\Verifier;
use Gcego\Workflows\Engine\AvailableTransition;
use Gcego\Workflows\Engine\WorkflowEngine;
use Gcego\Workflows\Exceptions\InvalidTransitionException;
use Gcego\Workflows\Exceptions\WorkflowAuthorizationException;
use Gcego\Workflows\Exceptions\WorkflowConflictException;
use Gcego\Workflows\Exceptions\WorkflowNotFoundException;
use Gcego\Workflows\Instances\InstanceStatus;
use Gcego\Workflows\Models\WorkflowInstance;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * A running gcego/workflows instance: its state and status, the append-only
 * history, which transitions a person may take now, and how the engine
 * refuses commands (reason codes, 403, 404 across tenants, 409 when stale).
 *
 * The instance is re-read from the database before every assertion.
 */
final class WorkflowTestable extends Testable
{
    public function __construct(Verifier $verifier, private readonly Container $container, private readonly WorkflowInstance $instance)
    {
        parent::__construct($verifier);
    }

    public function instance(): WorkflowInstance
    {
        return $this->instance->refresh();
    }

    public function assertState(string $state): self
    {
        $actual = $this->instance()->getAttribute('state');

        return $this->check($actual === $state, sprintf('assertState(%s)', $this->export($state)), sprintf('Expected the instance to be in [%s].', $state), ['state' => $state], ['state' => $actual]);
    }

    public function assertStatus(InstanceStatus $status): self
    {
        $actual = $this->instance()->getAttribute('status');

        return $this->check($actual === $status, sprintf('assertStatus(%s)', $status->name), sprintf('Expected the instance to be %s.', $status->value), ['status' => $status->value], ['status' => $actual instanceof InstanceStatus ? $actual->value : $actual]);
    }

    /**
     * The history event types, oldest first ("workflow.started", …). With
     * $exact false, $types must appear in this order but others may be
     * interleaved.
     *
     * @param  list<string>  $types
     */
    public function assertHistory(array $types, bool $exact = true): self
    {
        $actual = $this->history();
        $passed = $exact ? $actual === $types : $this->inOrder($types, $actual);

        return $this->check($passed, 'assertHistory('.$this->export($types).')', 'Expected these history events'.($exact ? '' : ' in this order').'.', ['history' => $types], ['history' => $actual]);
    }

    public function assertTransitionAvailable(string $transition, Model $user): self
    {
        $available = $this->available($user);

        return $this->check(array_key_exists($transition, $available) && $available[$transition] === null, sprintf('assertTransitionAvailable(%s)', $this->export($transition)), sprintf('Expected [%s] to be available to the user.', $transition), ['available' => true], ['transitions' => $available]);
    }

    /**
     * Not offered, or offered disabled; with $reason, disabled for it.
     */
    public function assertTransitionUnavailable(string $transition, Model $user, ?string $reason = null): self
    {
        $available = $this->available($user);
        $offered = array_key_exists($transition, $available);
        $passed = $reason === null ? ! $offered || $available[$transition] !== null : $offered && $available[$transition] === $reason;

        return $this->check($passed, sprintf('assertTransitionUnavailable(%s, %s)', $this->export($transition), $this->export($reason)), sprintf('Expected [%s] not to be available to the user.', $transition), ['reason' => $reason ?? '(any)'], ['transitions' => $available]);
    }

    /**
     * The command is refused by the engine. $reason is an
     * InvalidTransitionException reason ("state", "guard", "comment",
     * "self_approval"…), or "forbidden", "not_found" or "conflict".
     *
     * @param  Closure(WorkflowEngine, WorkflowInstance): mixed  $command
     */
    public function assertRefused(Closure $command, ?string $reason = null): self
    {
        $exception = $this->caught(fn () => $command($this->container->make(WorkflowEngine::class), $this->instance()));
        $actual = match (true) {
            $exception instanceof InvalidTransitionException => $exception->reason,
            $exception instanceof WorkflowAuthorizationException => 'forbidden',
            $exception instanceof WorkflowNotFoundException => 'not_found',
            $exception instanceof WorkflowConflictException => 'conflict',
            default => null,
        };

        return $this->check($actual !== null && ($reason === null || $actual === $reason), 'assertRefused('.$this->export($reason).')', 'Expected the workflow engine to refuse the command.', ['reason' => $reason ?? '(any)'], ['reason' => $actual, 'exception' => $this->describe($exception)]);
    }

    protected function subject(): string
    {
        return 'workflow';
    }

    protected function context(): array
    {
        return ['Workflow' => $this->instance->getAttribute('workflow'), 'Instance' => $this->instance->getAttribute('ulid'), 'Tenant' => $this->instance->getAttribute('tenant')];
    }

    /**
     * Transition name => null when enabled, the disabling reason otherwise.
     *
     * @return array<string, ?string>
     */
    private function available(Model $user): array
    {
        $available = [];

        foreach ($this->container->make(WorkflowEngine::class)->available($this->instance(), $user) as $transition) {
            if ($transition instanceof AvailableTransition) {
                $available[(string) $transition->transition->name()] = $transition->reason;
            }
        }

        return $available;
    }

    /**
     * @return list<string>
     */
    private function history(): array
    {
        /** @var list<string> $types */
        $types = array_values($this->instance->events()->orderBy('sequence')->pluck('type')->all());

        return $types;
    }

    /**
     * @param  list<string>  $expected
     * @param  list<string>  $actual
     */
    private function inOrder(array $expected, array $actual): bool
    {
        $position = 0;

        foreach ($actual as $type) {
            if (($expected[$position] ?? null) === $type) {
                $position++;
            }
        }

        return $position === count($expected);
    }
}
