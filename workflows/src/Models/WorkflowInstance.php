<?php

declare(strict_types=1);

namespace Gcego\Workflows\Models;

use Gcego\Workflows\Instances\InstanceStatus;
use Gcego\Workflows\Support\Keys;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A running (or finished) workflow for one subject. Only gcego/workflows
 * services write it, through compare-and-swap updates on lock_version.
 *
 * @property int $id
 * @property string $ulid
 * @property string $workflow
 * @property int $version
 * @property string $structure
 * @property string $subject_type
 * @property string $subject_id
 * @property string|null $tenant
 * @property string $state
 * @property InstanceStatus $status
 * @property int $lock_version
 * @property int $state_sequence
 * @property Carbon|null $state_entered_at
 * @property Carbon|null $deadline_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $suspended_at
 */
class WorkflowInstance extends Model
{
    protected $table = 'gcego_workflow_instances';

    protected $guarded = ['*'];

    protected $hidden = ['id', 'tenant', 'subject_type', 'subject_id', 'initiator_type', 'initiator_id', 'structure', 'running'];

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function initiator(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<WorkflowTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(WorkflowTask::class, 'instance_id');
    }

    /**
     * @return HasMany<WorkflowEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(WorkflowEvent::class, 'instance_id');
    }

    /**
     * @return HasMany<WorkflowDecision, $this>
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(WorkflowDecision::class, 'instance_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeInTenant(Builder $query, ?string $tenant): Builder
    {
        return $tenant === null ? $query->whereNull('tenant') : $query->where('tenant', $tenant);
    }

    public function isRunning(): bool
    {
        return $this->status === InstanceStatus::Active || $this->status === InstanceStatus::Suspended;
    }

    public function initiatedBy(?Model $user): bool
    {
        return $user !== null && $this->getAttribute('initiator_type') === $user->getMorphClass() && Keys::string($this->getAttribute('initiator_id')) === Keys::string($user->getKey());
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'lock_version' => 'integer',
            'state_sequence' => 'integer',
            'status' => InstanceStatus::class,
            'state_entered_at' => 'datetime',
            'deadline_at' => 'datetime',
            'started_at' => 'datetime',
            'suspended_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
