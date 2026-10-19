<?php

declare(strict_types=1);

namespace Gcego\Workflows\Engine;

use Closure;

/**
 * Side effects collected during a command and released only after its
 * transaction commits (discarded when it rolls back): Laravel events,
 * automation jobs and notifications. Nothing irreversible happens before
 * the commit.
 */
final class Effects
{
    /** @var list<object> */
    private array $events = [];

    /** @var list<Closure(): void> */
    private array $callbacks = [];

    public function event(object $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @param  Closure(): void  $callback
     */
    public function after(Closure $callback): void
    {
        $this->callbacks[] = $callback;
    }

    /**
     * @return list<object>
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * @return list<Closure(): void>
     */
    public function callbacks(): array
    {
        return $this->callbacks;
    }
}
