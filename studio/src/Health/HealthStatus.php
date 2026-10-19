<?php

declare(strict_types=1);

namespace Gcego\Studio\Health;

enum HealthStatus: string
{
    case Pass = 'pass';
    case Warning = 'warning';
    case Failure = 'failure';
    case Skipped = 'skipped';
}
