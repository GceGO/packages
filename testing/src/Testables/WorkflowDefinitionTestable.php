<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Gcego\Components\Validation\ValidationError;
use Gcego\Testing\Assertions\Verifier;
use Gcego\Workflows\Workflow;

/**
 * A gcego/workflows definition before it runs: structural validation,
 * reachability and transitions.
 */
final class WorkflowDefinitionTestable extends Testable
{
    public function __construct(Verifier $verifier, private readonly Workflow $workflow)
    {
        parent::__construct($verifier);
    }

    public function assertValid(): self
    {
        $errors = [...array_map(static fn (ValidationError $error): string => $error->format(), $this->workflow->validate()->errors()), ...$this->workflow->problems()];

        return $this->check($errors === [], 'assertValid()', 'Expected the workflow definition to be valid.', ['errors' => []], ['errors' => $errors]);
    }

    public function assertInitialState(string $state): self
    {
        return $this->check($this->workflow->initialState() === $state, sprintf('assertInitialState(%s)', $this->export($state)), 'Expected the initial state.', ['initial' => $state], ['initial' => $this->workflow->initialState()]);
    }

    public function assertEveryStateReachable(): self
    {
        $unreachable = $this->workflow->unreachableStates();

        return $this->check($unreachable === [], 'assertEveryStateReachable()', 'Expected every state to be reachable from the initial state.', ['unreachable' => []], ['unreachable' => $unreachable]);
    }

    public function assertTransition(string $name, string $from, ?string $to = null): self
    {
        $transition = $this->workflow->transition($name, $from);

        return $this->check($transition !== null && ($to === null || $transition->to() === $to), sprintf('assertTransition(%s, %s, %s)', $this->export($name), $this->export($from), $this->export($to)), sprintf('Expected the transition [%s] from [%s]%s.', $name, $from, $to !== null ? " to [$to]" : ''), ['to' => $to ?? '(any)'], ['to' => $transition?->to()]);
    }

    protected function subject(): string
    {
        return 'workflow';
    }

    protected function context(): array
    {
        return ['Workflow' => $this->workflow->workflowName()];
    }
}
