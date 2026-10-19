<?php

declare(strict_types=1);

namespace Gcego\Workflows\History;

use Gcego\Workflows\Actors\SystemActor;
use Gcego\Workflows\Engine\Effects;
use Gcego\Workflows\Events\WorkflowEventRecorded;
use Gcego\Workflows\Models\WorkflowEvent;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\Models\WorkflowTask;
use Gcego\Workflows\Support\Keys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Appends history events. Called inside the command's transaction while
 * the instance row is locked, so sequences are gap-free per instance (a
 * unique index backs that up). Metadata is limited to scalar values;
 * comments are stored, never secrets or request payloads.
 */
final readonly class HistoryRecorder
{
    /**
     * @param  array<string, scalar|null|list<scalar>>  $metadata
     */
    public function record(
        WorkflowInstance $instance,
        string $type,
        Model|SystemActor|null $actor,
        string $correlation,
        Effects $effects,
        ?string $from = null,
        ?string $to = null,
        ?WorkflowTask $task = null,
        array $metadata = [],
    ): WorkflowEvent {
        $sequence = Keys::int(WorkflowEvent::query()->where('instance_id', $instance->id)->max('sequence')) + 1;

        $event = new WorkflowEvent;
        $event->forceFill([
            'ulid' => (string) Str::ulid(),
            'instance_id' => $instance->id,
            'sequence' => $sequence,
            'tenant' => $instance->tenant,
            'type' => $type,
            'actor_kind' => $actor instanceof SystemActor ? 'system' : ($actor === null ? 'none' : 'user'),
            'actor_type' => $actor instanceof Model ? $actor->getMorphClass() : null,
            'actor_id' => $actor instanceof Model ? Keys::string($actor->getKey()) : null,
            'actor_name' => $actor instanceof SystemActor ? $actor->name : null,
            'from_state' => $from,
            'to_state' => $to,
            'task_id' => $task?->id,
            'metadata' => array_filter($metadata, static fn (mixed $value): bool => $value !== null) ?: null,
            'correlation' => $correlation,
        ])->save();

        $effects->event(new WorkflowEventRecorded($instance->ulid, $instance->workflow, $instance->tenant, $type, $sequence, $correlation, $from, $to));

        return $event;
    }
}
