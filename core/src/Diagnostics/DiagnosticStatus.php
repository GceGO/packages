<?php

declare(strict_types=1);

namespace Gcego\Core\Diagnostics;

enum DiagnosticStatus: string
{
    case Passed = 'passed';
    case Warning = 'warning';
    case Failed = 'failed';
}
