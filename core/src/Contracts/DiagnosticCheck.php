<?php

declare(strict_types=1);

namespace Gcego\Core\Contracts;

use Gcego\Core\Diagnostics\DiagnosticResult;

interface DiagnosticCheck
{
    /** A stable lowercase dotted name, prefixed with the contributing package. */
    public function id(): string;

    /** Return the result using the same check id. Avoid secrets in messages/details. */
    public function run(): DiagnosticResult;
}
