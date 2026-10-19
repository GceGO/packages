<?php

declare(strict_types=1);

namespace Gcego\Workflows\Events;

/**
 * A history event was committed (one per event, after the transaction).
 * Carries identifiers only: listeners load what they are allowed to see.
 */
final readonly class WorkflowEventRecorded
{
    public function __construct(
        public string $instance,
        public string $workflow,
        public ?string $tenant,
        public string $type,
        public int $sequence,
        public string $correlation,
        public ?string $from = null,
        public ?string $to = null,
    ) {}
}
