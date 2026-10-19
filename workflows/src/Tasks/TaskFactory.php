<?php

declare(strict_types=1);

namespace Gcego\Workflows\Tasks;

use Gcego\Workflows\Actors\SystemActor;
use Gcego\Workflows\Engine\Effects;
use Gcego\Workflows\History\EventType;
use Gcego\Workflows\History\HistoryRecorder;
use Gcego\Workflows\Integration\WorkflowNotifier;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\Models\WorkflowTask;
use Gcego\Workflows\Support\Duration;
use Gcego\Workflows\Support\Keys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Creates task rows (one per assignee, or one pool task) with their
 * history event and an after-commit notification.
 */
final readonly class TaskFactory
{
    public function __construct(private HistoryRecorder $history, private WorkflowNotifier $notifier) {}

    /**
     * @param  array{kind: string, name: string, stage?: string|null, stage_index?: int, round?: int, priority?: string, due?: string|null, delegations?: int, delegated_by?: Model|null}  $attributes
     */
    public function create(WorkflowInstance $instance, ?Model $assignee, array $attributes, Model|SystemActor|null $actor, string $correlation, Effects $effects): WorkflowTask
    {
        $task = new WorkflowTask;
        $task->forceFill([
            'ulid' => (string) Str::ulid(),
            'instance_id' => $instance->id,
            'tenant' => $instance->tenant,
            'kind' => $attributes['kind'],
            'name' => $attributes['name'],
            'stage' => $attributes['stage'] ?? null,
            'stage_index' => $attributes['stage_index'] ?? 0,
            'round' => $attributes['round'] ?? 1,
            'state' => $instance->state,
            'assignee_type' => $assignee?->getMorphClass(),
            'assignee_id' => $assignee === null ? null : Keys::string($assignee->getKey()),
            'pool' => $assignee === null,
            'status' => TaskStatus::Open,
            'priority' => $attributes['priority'] ?? 'normal',
            'due_at' => isset($attributes['due']) ? Duration::after($attributes['due']) : null,
            'delegations' => $attributes['delegations'] ?? 0,
            'delegated_by_type' => ($attributes['delegated_by'] ?? null)?->getMorphClass(),
            'delegated_by_id' => ($by = $attributes['delegated_by'] ?? null) === null ? null : Keys::string($by->getKey()),
        ])->save();
        $task->setRelation('instance', $instance);

        $this->history->record($instance, EventType::TaskCreated, $actor, $correlation, $effects, task: $task, metadata: ['task' => $task->ulid, 'kind' => $task->kind, 'name' => $task->name, 'stage' => $task->stage, 'pool' => $task->pool]);

        if ($assignee !== null) {
            $this->notifier->assigned($task, $assignee, $effects);
        }

        return $task;
    }
}
