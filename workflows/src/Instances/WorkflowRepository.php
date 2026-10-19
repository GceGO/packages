<?php

declare(strict_types=1);

namespace Gcego\Workflows\Instances;

use Gcego\Workflows\Access\WorkflowAuthorizer;
use Gcego\Workflows\Engine\CommandRunner;
use Gcego\Workflows\Exceptions\WorkflowException;
use Gcego\Workflows\Exceptions\WorkflowNotFoundException;
use Gcego\Workflows\Models\WorkflowEvent;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\Models\WorkflowTask;
use Gcego\Workflows\Registry\WorkflowDefinitions;
use Gcego\Workflows\Support\Keys;
use Gcego\Workflows\WorkflowsConfig;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read access, always through the viewer: instances of the current tenant
 * the viewer may see (else "not found", so other tenants' instances never
 * show), their history, and the instances of a subject.
 */
final readonly class WorkflowRepository
{
    public function __construct(
        private WorkflowDefinitions $definitions,
        private WorkflowAuthorizer $authorizer,
        private CommandRunner $runner,
        private WorkflowsConfig $config,
    ) {}

    /**
     * @throws WorkflowNotFoundException
     */
    public function find(string $ulid, Model $viewer): WorkflowInstance
    {
        $instance = preg_match('/^[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}$/', $ulid) === 1 ? $this->scoped()->where('ulid', strtoupper($ulid))->first() : null;

        if ($instance === null || ! $this->canView($instance, $viewer)) {
            throw WorkflowNotFoundException::make('workflow instance');
        }

        return $instance;
    }

    public function findTask(string $ulid, Model $viewer): WorkflowTask
    {
        $task = preg_match('/^[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}$/', $ulid) === 1 ? WorkflowTask::query()->where('ulid', strtoupper($ulid))->with('instance')->first() : null;

        if ($task === null || ! $this->canView($task->instance, $viewer)) {
            throw WorkflowNotFoundException::make('task');
        }

        return $task;
    }

    public function canView(WorkflowInstance $instance, Model $viewer): bool
    {
        try {
            $this->runner->scope($instance, $viewer);

            return $this->authorizer->canView($this->definitions->resolve($instance->workflow), $instance, $instance->subject, $viewer);
        } catch (WorkflowException) {
            return false;
        }
    }

    public function canManage(WorkflowInstance $instance, Model $viewer): bool
    {
        try {
            $this->runner->scope($instance, $viewer);

            return $this->authorizer->canManage($this->definitions->resolve($instance->workflow), $instance, $instance->subject, $viewer);
        } catch (WorkflowException) {
            return false;
        }
    }

    /**
     * Instances of a subject the viewer may see, newest first.
     *
     * @return list<WorkflowInstance>
     */
    public function forSubject(Model $subject, Model $viewer, ?string $workflow = null): array
    {
        $instances = $this->scoped()->where('subject_type', $subject->getMorphClass())->where('subject_id', Keys::string($subject->getKey()))
            ->when($workflow !== null, static fn (Builder $query) => $query->where('workflow', $workflow))
            ->latest('id')->limit(50)->get();

        return array_values($instances->filter(function (WorkflowInstance $instance) use ($subject, $viewer): bool {
            $instance->setRelation('subject', $subject);

            return $this->canView($instance, $viewer);
        })->all());
    }

    /**
     * The latest instance of a subject the viewer may see.
     */
    public function latestFor(Model $subject, Model $viewer, ?string $workflow = null): ?WorkflowInstance
    {
        return $this->forSubject($subject, $viewer, $workflow)[0] ?? null;
    }

    /**
     * History of an instance the caller already authorized, newest first.
     *
     * @return LengthAwarePaginator<int, WorkflowEvent>
     */
    public function history(WorkflowInstance $instance, int $page = 1): LengthAwarePaginator
    {
        return $instance->events()->with('actor')->orderByDesc('sequence')->paginate($this->config->historyPerPage, ['*'], 'history', max(1, $page));
    }

    /**
     * @return Builder<WorkflowInstance>
     */
    public function scoped(): Builder
    {
        $query = WorkflowInstance::query();

        return $this->config->enforceTenantScope ? $query->inTenant($this->runner->currentTenant()) : $query;
    }
}
