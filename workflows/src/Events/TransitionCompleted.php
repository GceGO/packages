<?php

declare(strict_types=1);

namespace Gcego\Workflows\Events;

use Gcego\Workflows\Engine\TransitionResult;

/**
 * A transition committed (after the transaction).
 */
final readonly class TransitionCompleted
{
    public function __construct(public string $workflow, public ?string $tenant, public TransitionResult $result, public string $actorKind) {}
}
