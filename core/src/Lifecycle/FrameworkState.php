<?php

declare(strict_types=1);

namespace Gcego\Core\Lifecycle;

/**
 * GCEGO's own readiness inside a running Laravel application.
 *
 *     Uninitialized -> Registered -> Booting -> Booted
 *                          ^            |
 *                          +-- (disabled)
 *                                       v
 *                                    Failed -> Booting (retry)
 */
enum FrameworkState: string
{
    case Uninitialized = 'uninitialized';
    case Registered = 'registered';
    case Booting = 'booting';
    case Booted = 'booted';
    case Failed = 'failed';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Uninitialized => $next === self::Registered,
            self::Registered, self::Failed => $next === self::Booting,
            self::Booting => in_array($next, [self::Booted, self::Failed, self::Registered], true),
            self::Booted => false,
        };
    }
}
