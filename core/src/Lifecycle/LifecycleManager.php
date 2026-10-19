<?php

declare(strict_types=1);

namespace Gcego\Core\Lifecycle;

use Gcego\Core\Exceptions\InvalidLifecycleException;
use Throwable;

/**
 * The single source of truth for GCEGO's lifecycle state.
 *
 * Laravel's provider lifecycle stays authoritative: this only records how far
 * GCEGO itself has progressed within it.
 */
final class LifecycleManager
{
    private FrameworkState $state = FrameworkState::Uninitialized;

    private ?Throwable $failure = null;

    public function state(): FrameworkState
    {
        return $this->state;
    }

    public function is(FrameworkState $state): bool
    {
        return $this->state === $state;
    }

    public function isBooted(): bool
    {
        return $this->state === FrameworkState::Booted;
    }

    /**
     * The exception that caused the most recent failed boot, if the framework is in the Failed state.
     */
    public function failure(): ?Throwable
    {
        return $this->failure;
    }

    /**
     * Idempotent: only the first call has an effect.
     */
    public function markRegistered(): void
    {
        if ($this->state === FrameworkState::Uninitialized) {
            $this->transitionTo(FrameworkState::Registered);
        }
    }

    public function beginBoot(): void
    {
        $this->transitionTo(FrameworkState::Booting);
        $this->failure = null;
    }

    public function completeBoot(): void
    {
        $this->transitionTo(FrameworkState::Booted);
    }

    /**
     * Abort a boot that decided not to proceed (for example because GCEGO is disabled).
     */
    public function cancelBoot(): void
    {
        $this->transitionTo(FrameworkState::Registered);
    }

    public function fail(Throwable $failure): void
    {
        $this->transitionTo(FrameworkState::Failed);
        $this->failure = $failure;
    }

    /**
     * @throws InvalidLifecycleException
     */
    public function transitionTo(FrameworkState $next): void
    {
        if (! $this->state->canTransitionTo($next)) {
            throw InvalidLifecycleException::transition($this->state, $next);
        }

        $this->state = $next;
    }
}
