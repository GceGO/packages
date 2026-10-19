<?php

declare(strict_types=1);

namespace Gcego\Workflows\Livewire;

use Gcego\Workflows\Approvals\Decision;
use Gcego\Workflows\Engine\WorkflowEngine;
use Gcego\Workflows\Exceptions\WorkflowException;
use Gcego\Workflows\Instances\WorkflowContext;
use Gcego\Workflows\Instances\WorkflowRepository;
use Gcego\Workflows\Livewire\Concerns\ResolvesViewer;
use Gcego\Workflows\Models\WorkflowTask;
use Gcego\Workflows\Support\Keys;
use Gcego\Workflows\Support\WorkflowLabels;
use Gcego\Workflows\Support\WorkflowStyles;
use Gcego\Workflows\Tasks\TaskAssigner;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * <livewire:gcego-workflow-approval :task="$ulid" />: one approval or
 * task of the signed-in user: approve, reject, request changes, abstain
 * (when allowed), complete, or delegate to an eligible person (the
 * approval's reviewers, never a free-form identity).
 */
final class WorkflowApproval extends Component
{
    use ResolvesViewer;

    #[Locked]
    public string $task = '';

    public string $comment = '';

    public string $delegateTo = '';

    public ?string $message = null;

    public function mount(string $task): void
    {
        $this->task = $task;
    }

    public function decide(mixed $decision): void
    {
        $this->reset('message');
        $this->resetErrorBag();
        $task = $this->current();
        $decision = is_string($decision) ? Decision::tryFrom($decision) : null;

        if ($task === null || $decision === null) {
            return;
        }

        $this->run(fn () => app(WorkflowEngine::class)->decide($task, $decision, $this->viewer(), self::cleanComment($this->comment)), 'decided');
    }

    public function complete(): void
    {
        $this->reset('message');
        $this->resetErrorBag();
        $task = $this->current();

        if ($task !== null) {
            $this->run(fn () => app(WorkflowEngine::class)->completeTask($task, $this->viewer(), self::cleanComment($this->comment)), 'completed');
        }
    }

    public function delegate(): void
    {
        $this->reset('message');
        $this->resetErrorBag();
        $task = $this->current();
        $to = collect($task === null ? [] : $this->candidates($task))->first(fn (Model $person): bool => $person->getMorphClass().'#'.Keys::string($person->getKey()) === $this->delegateTo);

        if ($task === null) {
            return;
        }

        if (! $to instanceof Model) {
            $this->addError('approval', (string) __('gcego-workflows::messages.errors.assignee'));

            return;
        }

        $this->run(fn () => app(WorkflowEngine::class)->delegate($task, $this->viewer(), $to, self::cleanComment($this->comment)), 'delegated');
    }

    public function render(): View
    {
        $task = $this->current();
        $workflow = $task === null ? null : app(WorkflowLabels::class)->workflow($task->instance->workflow);
        $approval = $task !== null && $task->isApproval() ? $workflow?->approval($task->name) : null;
        $workable = $task !== null && app(WorkflowEngine::class)->canWorkOn($task, $this->viewer());

        return view('gcego-workflows::livewire.approval', [
            'record' => $workable ? $task : null,
            'approval' => $approval,
            'candidates' => $workable && $task !== null && $task->isAssignedTo($this->viewer()) && (! $task->isApproval() || ($approval?->allowsDelegation() ?? false)) ? $this->candidates($task) : [],
            'labels' => app(WorkflowLabels::class),
            'styles' => app(WorkflowStyles::class),
        ]);
    }

    /**
     * @param  \Closure(): mixed  $command
     */
    private function run(\Closure $command, string $message): void
    {
        try {
            $command();
            $this->comment = '';
            $this->delegateTo = '';
            $this->message = (string) __('gcego-workflows::messages.'.$message);
            $this->dispatch('gcego-workflow-updated');
        } catch (WorkflowException $exception) {
            $this->addError('approval', $this->refusal($exception));
        }
    }

    /**
     * Eligible people to delegate to: the step's own reviewers or assignees (resolved and checked on the server).
     *
     * @return list<Model>
     */
    private function candidates(WorkflowTask $task): array
    {
        $workflow = app(WorkflowLabels::class)->workflow($task->instance->workflow);

        if ($workflow === null) {
            return [];
        }

        $resolver = $task->isApproval()
            ? ($workflow->approval($task->name)?->stages()[$task->stage_index] ?? null)?->reviewers()
            : collect($workflow->state($task->state)?->tasks() ?? [])->first(static fn ($definition): bool => $definition->nodeName() === $task->name)?->assign();

        if ($resolver === null) {
            return [];
        }

        $people = app(TaskAssigner::class)->resolve($resolver, new WorkflowContext($task->instance, $workflow, $this->viewer()), app(WorkflowEngine::class)->requirementsOf($task, $workflow), $task->isApproval());

        return array_values(array_filter($people, fn (Model $person): bool => ! $person->is($this->viewer())));
    }

    private function current(): ?WorkflowTask
    {
        try {
            return app(WorkflowRepository::class)->findTask($this->task, $this->viewer());
        } catch (WorkflowException) {
            return null;
        }
    }
}
