<?php

declare(strict_types=1);

namespace Gcego\Workflows\Tasks;

use Gcego\Workflows\Engine\CommandRunner;
use Gcego\Workflows\Engine\WorkflowEngine;
use Gcego\Workflows\Instances\InstanceStatus;
use Gcego\Workflows\Models\WorkflowTask;
use Gcego\Workflows\Support\Keys;
use Gcego\Workflows\WorkflowsConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * A person's work in the current tenant: open tasks and approvals assigned
 * to them, plus pool work they are eligible for (checked one by one with
 * the same rules as the commands, so the inbox never offers what a
 * command would refuse). Urgent and overdue first.
 */
final readonly class Inbox
{
    /** Pool candidates examined per request. */
    public const int PoolLimit = 200;

    public function __construct(private WorkflowEngine $engine, private CommandRunner $runner, private WorkflowsConfig $config) {}

    /**
     * @return LengthAwarePaginator<int, WorkflowTask>
     */
    public function for(Model $user, int $page = 1, ?string $filter = null): LengthAwarePaginator
    {
        $tasks = $this->tasks($user, $filter);
        $perPage = $this->config->inboxPerPage;
        $page = max(1, $page);

        return new LengthAwarePaginator(array_values(array_slice($tasks, ($page - 1) * $perPage, $perPage)), count($tasks), $perPage, $page);
    }

    public function count(Model $user): int
    {
        return count($this->tasks($user, null));
    }

    /**
     * @return list<WorkflowTask>
     */
    private function tasks(Model $user, ?string $filter): array
    {
        $base = fn (): Builder => WorkflowTask::query()->open()->with('instance')
            ->when($this->config->enforceTenantScope, fn (Builder $query) => ($tenant = $this->runner->currentTenant()) === null ? $query->whereNull('tenant') : $query->where('tenant', $tenant))
            ->whereHas('instance', static fn (Builder $query) => $query->where('status', InstanceStatus::Active->value))
            ->when($filter === 'approvals', static fn (Builder $query) => $query->where('kind', WorkflowTask::Approval))
            ->when($filter === 'tasks', static fn (Builder $query) => $query->where('kind', WorkflowTask::Task))
            ->when($filter === 'overdue', static fn (Builder $query) => $query->whereNotNull('overdue_at'));

        $assigned = $base()->where('pool', false)->where('assignee_type', $user->getMorphClass())->where('assignee_id', Keys::string($user->getKey()))->limit(500)->get();
        $pool = $base()->where('pool', true)->latest('id')->limit(self::PoolLimit)->get();

        $tasks = [...$assigned->all(), ...$pool->all()];
        $tasks = array_values(array_filter($tasks, fn (WorkflowTask $task): bool => $task->state === $task->instance->state && $this->engine->canWorkOn($task, $user)));

        $rank = array_flip(array_reverse(Task::Priorities));
        usort($tasks, static fn (WorkflowTask $a, WorkflowTask $b): int => [($a->overdue_at === null ? 1 : 0), $rank[$a->priority] ?? 9, $a->due_at?->getTimestamp() ?? PHP_INT_MAX, $a->id]
            <=> [($b->overdue_at === null ? 1 : 0), $rank[$b->priority] ?? 9, $b->due_at?->getTimestamp() ?? PHP_INT_MAX, $b->id]);

        return $tasks;
    }
}
