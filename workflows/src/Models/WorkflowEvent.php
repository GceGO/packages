<?php

declare(strict_types=1);

namespace Gcego\Workflows\Models;

use Gcego\Workflows\Exceptions\WorkflowDefinitionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * An append-only history event: once written, it is never updated or
 * deleted through the model (deleting the instance removes its history).
 * Ordered by sequence within an instance. This is application-level
 * immutability, not a cryptographic guarantee.
 *
 * @property int $id
 * @property string $ulid
 * @property int $instance_id
 * @property int $sequence
 * @property string $type
 * @property string $actor_kind
 * @property string|null $actor_name
 * @property string|null $from_state
 * @property string|null $to_state
 * @property array<string, mixed>|null $metadata
 * @property string $correlation
 * @property Carbon|null $created_at
 */
class WorkflowEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'gcego_workflow_events';

    protected $guarded = ['*'];

    protected $hidden = ['id', 'instance_id', 'tenant', 'actor_type', 'actor_id', 'task_id'];

    protected static function booted(): void
    {
        static::updating(static fn (): bool => throw WorkflowDefinitionException::invalid('Workflow history', 'events are append-only.'));
        static::deleting(static fn (): bool => throw WorkflowDefinitionException::invalid('Workflow history', 'events are append-only.'));
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return ['metadata' => 'array', 'sequence' => 'integer', 'created_at' => 'datetime'];
    }
}
