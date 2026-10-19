<?php

declare(strict_types=1);

namespace Gcego\Workflows\Livewire;

use Gcego\Workflows\Access\WorkflowAuthorizer;
use Gcego\Workflows\Engine\CommandRunner;
use Gcego\Workflows\Engine\WorkflowEngine;
use Gcego\Workflows\Exceptions\WorkflowException;
use Gcego\Workflows\Instances\WorkflowRepository;
use Gcego\Workflows\Livewire\Concerns\ResolvesViewer;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\Support\Keys;
use Gcego\Workflows\Support\WorkflowLabels;
use Gcego\Workflows\Support\WorkflowStyles;
use Gcego\Workflows\Workflow;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * <livewire:gcego-workflow-panel :instance="$ulid" /> or
 * <livewire:gcego-workflow-panel :subject="$record" workflow="registration" />:
 * the current state, the transitions the user may run (with comment and
 * confirmation), their approvals and tasks, and management commands.
 *
 * The browser sends only a transition name, a comment and the version
 * it saw; the server re-reads the instance, re-authorizes and refuses
 * stale screens (409 message, then shows the new state).
 */
final class WorkflowPanel extends Component
{
    use ResolvesViewer;

    #[Locked]
    public ?string $instance = null;

    /** The instance version the user is looking at (optimistic locking). */
    #[Locked]
    public int $version = 0;

    /** A workflow to offer starting, for a subject without a running instance. */
    #[Locked]
    public ?string $workflow = null;

    #[Locked]
    public ?string $subjectType = null;

    #[Locked]
    public ?string $subjectId = null;

    public string $comment = '';

    public ?string $message = null;

    public function mount(?string $instance = null, ?Model $subject = null, ?string $workflow = null): void
    {
        $this->workflow = $workflow;

        if ($subject !== null) {
            $this->subjectType = $subject->getMorphClass();
            $this->subjectId = Keys::string($subject->getKey());
            $instance ??= app(WorkflowRepository::class)->latestFor($subject, $this->viewer(), $workflow)?->ulid;
        }

        $this->instance = $instance;
        $this->version = $this->current()->lock_version ?? 0;
    }

    public function start(): void
    {
        $this->reset('message');
        $this->resetErrorBag();
        $subject = $this->subject();

        if ($subject === null || $this->workflow === null) {
            return;
        }

        try {
            $instance = app(WorkflowEngine::class)->start($this->workflow, $subject, $this->viewer());
            $this->instance = $instance->ulid;
            $this->version = $instance->lock_version;
            $this->message = (string) __('gcego-workflows::messages.started');
            $this->dispatch('gcego-workflow-updated', instance: $instance->ulid);
        } catch (WorkflowException $exception) {
            $this->addError('workflow', $this->refusal($exception));
        }
    }

    public function runTransition(mixed $name): void
    {
        $this->reset('message');
        $this->resetErrorBag();
        $instance = $this->current();

        if ($instance === null || ! is_string($name)) {
            return;
        }

        try {
            $result = app(WorkflowEngine::class)->transition($instance, $name, $this->viewer(), self::cleanComment($this->comment), $this->version);
            $this->comment = '';
            $this->version = $result->version;
            $this->message = (string) __('gcego-workflows::messages.transitioned', ['state' => app(WorkflowLabels::class)->state($instance->workflow, $result->to)]);
            $this->dispatch('gcego-workflow-updated', instance: $instance->ulid);
        } catch (WorkflowException $exception) {
            $this->addError('workflow', $this->refusal($exception));
            $this->version = $this->current()->lock_version ?? $this->version;
        }
    }

    public function manage(mixed $command): void
    {
        $this->reset('message');
        $this->resetErrorBag();
        $instance = $this->current();

        if ($instance === null || ! in_array($command, ['suspend', 'resume', 'cancel'], true)) {
            return;
        }

        try {
            $engine = app(WorkflowEngine::class);
            $comment = self::cleanComment($this->comment);
            $result = match ($command) {
                'suspend' => $engine->suspend($instance, $this->viewer(), $comment, $this->version),
                'resume' => $engine->resume($instance, $this->viewer(), $comment, $this->version),
                default => $engine->cancel($instance, $this->viewer(), $comment, $this->version),
            };
            $this->comment = '';
            $this->version = $result->version;
            $this->message = (string) __('gcego-workflows::messages.managed.'.$command);
            $this->dispatch('gcego-workflow-updated', instance: $instance->ulid);
        } catch (WorkflowException $exception) {
            $this->addError('workflow', $this->refusal($exception));
            $this->version = $this->current()->lock_version ?? $this->version;
        }
    }

    #[On('gcego-workflow-updated')]
    public function refresh(): void
    {
        $this->version = $this->current()->lock_version ?? $this->version;
    }

    public function render(): View
    {
        $instance = $this->current();
        $viewer = $this->viewer();
        $engine = app(WorkflowEngine::class);
        $repository = app(WorkflowRepository::class);
        $subject = $this->subject();
        $workflow = $this->workflow === null ? null : app(WorkflowLabels::class)->workflow($this->workflow);

        return view('gcego-workflows::livewire.panel', [
            'record' => $instance,
            'stale' => $instance !== null && $instance->lock_version !== $this->version,
            'available' => $instance === null ? [] : $engine->available($instance, $viewer),
            'tasks' => $instance === null ? [] : $instance->tasks()->open()->get()->each(static fn ($task) => $task->setRelation('instance', $instance))->filter(static fn ($task): bool => $engine->canWorkOn($task, $viewer))->values()->all(),
            'manageable' => $instance !== null && $instance->isRunning() && $repository->canManage($instance, $viewer),
            'startable' => $instance === null || ! $instance->isRunning() ? ($workflow !== null && $subject !== null && $this->canStart($workflow, $subject)) : false,
            'definition' => $workflow,
            'labels' => app(WorkflowLabels::class),
            'styles' => app(WorkflowStyles::class),
        ]);
    }

    private function current(): ?WorkflowInstance
    {
        if ($this->instance === null) {
            return null;
        }

        try {
            return app(WorkflowRepository::class)->find($this->instance, $this->viewer());
        } catch (WorkflowException) {
            return null;
        }
    }

    private function subject(): ?Model
    {
        if ($this->subjectType === null || $this->subjectId === null) {
            return null;
        }

        $class = Relation::getMorphedModel($this->subjectType) ?? $this->subjectType;

        return is_subclass_of($class, Model::class) ? $class::query()->find($this->subjectId) : null;
    }

    private function canStart(Workflow $workflow, Model $subject): bool
    {
        return app(WorkflowAuthorizer::class)->canStart($workflow, $subject, $this->viewer(), app(CommandRunner::class)->currentTenant())
            && ($workflow->allowsConcurrentInstances() || app(WorkflowEngine::class)->running($workflow->workflowName(), $subject) === null);
    }
}
