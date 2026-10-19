<?php

declare(strict_types=1);

namespace Gcego\Workflows\Models\Concerns;

use Gcego\Workflows\Models\WorkflowInstance;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * For subject models: their workflow instances (eager loadable, so the
 * tables column and infolist entry avoid one query per row). Reading the
 * relation is not authorization: use WorkflowRepository for viewers.
 */
trait HasWorkflows
{
    /**
     * @return MorphMany<WorkflowInstance, $this>
     */
    public function workflowInstances(): MorphMany
    {
        return $this->morphMany(WorkflowInstance::class, 'subject');
    }
}
