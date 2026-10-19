<?php

declare(strict_types=1);

namespace Gcego\Workflows\Events;

/**
 * A command was refused (authorization, guard, conflict, invalid state...).
 * For monitoring; holds no subject data or comments.
 */
final readonly class WorkflowCommandRefused
{
    public function __construct(
        public string $command,
        public ?string $instance,
        public ?string $workflow,
        public string $reason,
        public string $actorKind,
    ) {}
}
