<?php

declare(strict_types=1);

namespace Gcego\Workflows\Support;

use Gcego\Workflows\Instances\WorkflowRepository;
use Gcego\Workflows\Models\WorkflowInstance;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * The latest instance of a workflow for a record the viewer may see (for
 * columns and entries), using the eager-loaded HasWorkflows relation when present.
 */
final readonly class SubjectState
{
    public function __construct(private WorkflowRepository $repository) {}

    public function latest(mixed $record, string $workflow): ?WorkflowInstance
    {
        $viewer = auth()->user();

        if (! $record instanceof Model || ! $viewer instanceof Model) {
            return null;
        }

        if ($record->relationLoaded('workflowInstances') && ($loaded = $record->getRelation('workflowInstances')) instanceof Collection) {
            $instance = $loaded->filter(static fn (mixed $instance): bool => $instance instanceof WorkflowInstance && $instance->workflow === $workflow)->sortByDesc('id')->first();

            if (! $instance instanceof WorkflowInstance) {
                return null;
            }

            $instance->setRelation('subject', $record);

            return $this->repository->canView($instance, $viewer) ? $instance : null;
        }

        return $this->repository->latestFor($record, $viewer, $workflow);
    }
}
