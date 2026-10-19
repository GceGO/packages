<?php

declare(strict_types=1);

namespace Gcego\Components\Support;

/**
 * Sentinel distinguishing "no default declared" from an explicit null default.
 */
enum Absent
{
    case Value;
}
