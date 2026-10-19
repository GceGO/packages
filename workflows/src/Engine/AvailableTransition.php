<?php

declare(strict_types=1);

namespace Gcego\Workflows\Engine;

use Gcego\Workflows\Transitions\Transition;

/**
 * A transition a person is authorized for in the current state; $reason
 * explains why it cannot run yet (a guard, a pending approval), or null.
 */
final readonly class AvailableTransition
{
    public function __construct(public Transition $transition, public ?string $reason = null) {}

    public function enabled(): bool
    {
        return $this->reason === null;
    }
}
