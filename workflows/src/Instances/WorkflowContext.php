<?php

declare(strict_types=1);

namespace Gcego\Workflows\Instances;

use Gcego\Workflows\Actors\SystemActor;
use Gcego\Workflows\Models\WorkflowInstance;
use Gcego\Workflows\Transitions\Transition;
use Gcego\Workflows\Workflow;
use Illuminate\Database\Eloquent\Model;

/**
 * What guards, conditions, assignment resolvers and handlers see: the
 * instance, its definition, its subject (loaded on demand), the actor and
 * the command's comment and input. Read-only.
 */
final class WorkflowContext
{
    private ?Model $subject = null;

    private bool $loaded = false;

    /**
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        public readonly WorkflowInstance $instance,
        public readonly Workflow $workflow,
        public readonly Model|SystemActor|null $actor,
        public readonly ?Transition $transition = null,
        public readonly ?string $comment = null,
        public readonly array $input = [],
    ) {}

    public function subject(): ?Model
    {
        if (! $this->loaded) {
            $subject = $this->instance->subject;
            $this->subject = $subject instanceof Model ? $subject : null;
            $this->loaded = true;
        }

        return $this->subject;
    }

    public function state(): string
    {
        return $this->instance->state;
    }

    public function tenant(): ?string
    {
        return $this->instance->tenant;
    }

    public function user(): ?Model
    {
        return $this->actor instanceof Model ? $this->actor : null;
    }

    public function isSystem(): bool
    {
        return $this->actor instanceof SystemActor;
    }
}
