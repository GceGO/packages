<?php

declare(strict_types=1);

namespace Gcego\Workflows\Instances;

enum InstanceStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    /** Reached a terminal state. */
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
