<?php

declare(strict_types=1);

namespace Gcego\Cli\Diagnostics;

enum DiagnosticStatus: string
{
    case Pass = 'pass';
    case Warning = 'warning';
    case Failure = 'failure';
    case Skipped = 'skipped';
    case NotApplicable = 'not_applicable';
}
