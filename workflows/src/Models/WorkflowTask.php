<?php

declare(strict_types=1);

namespace Gcego\Workflows\Models;

use Gcego\Workflows\Support\Keys;
use Gcego\Workflows\Tasks\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Human work of an instance: an approval decision ("approval") or a task
 * ("task"). Assigned to one person, or a pool open to everyone who
 * satisfies the step's requirements.
 *
 * @property int $id
 * @property string $ulid
 * @property int $instance_id
 * @property string|null $tenant
 * @property string $kind
 * @property string $name
 * @property string|null $stage
 * @property int $stage_index
 * @property int $round
 * @property string $state
 * @property bool $pool
 * @property TaskStatus $status
 * @property string $priority
 * @property int $delegations
 * @property Carbon|null $due_at
 * @property Carbon|null $overdue_at
 * @property Carbon|null $escalated_at
 * @property Carbon|null $completed_at
 * @property WorkflowInstance $instance
 */
class WorkflowTask extends Model
{
    public const string Approval = 'approval';

    public const string Task = 'task';

    protected $table = 'gcego_workflow_tasks';

    protected $guarded = ['*'];

    protected $hidden = ['id', 'instance_id', 'tenant', 'assignee_type', 'assignee_id', 'delegated_by_type', 'delegated_by_id'];

    /**
     * @return BelongsTo<WorkflowInstance, $this>
     */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'instance_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function assignee(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [TaskStatus::Open->value, TaskStatus::InProgress->value]);
    }

    public function isOpen(): bool
    {
        return $this->status === TaskStatus::Open || $this->status === TaskStatus::InProgress;
    }

    public function isAssignedTo(?Model $user): bool
    {
        return $user !== null && ! $this->pool && $this->getAttribute('assignee_type') === $user->getMorphClass() && Keys::string($this->getAttribute('assignee_id')) === Keys::string($user->getKey());
    }

    public function isApproval(): bool
    {
        return $this->kind === self::Approval;
    }

    protected function casts(): array
    {
        return [
            'pool' => 'boolean',
            'status' => TaskStatus::class,
            'stage_index' => 'integer',
            'round' => 'integer',
            'delegations' => 'integer',
            'due_at' => 'datetime',
            'overdue_at' => 'datetime',
            'escalated_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
