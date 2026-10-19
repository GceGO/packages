<?php

declare(strict_types=1);

namespace Gcego\Workflows\Tasks;

enum TaskStatus: string
{
    /** Waiting for its assignee (or anyone eligible, for pool tasks). */
    case Open = 'open';
    /** Taken by someone (pool tasks they claimed). */
    case InProgress = 'in_progress';
    case Completed = 'completed';
    /** Closed without a decision (state left, approval decided by others, workflow cancelled). */
    case Cancelled = 'cancelled';
    /** Its deadline passed and it was escalated to someone else. */
    case Escalated = 'escalated';
    /** Passed on to someone else. */
    case Delegated = 'delegated';
}
