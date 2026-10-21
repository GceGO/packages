<?php

declare(strict_types=1);

namespace Gcego\Core\Tests\Fixtures;

use Closure;
use Gcego\Core\Contracts\DiagnosticCheck;
use Gcego\Core\Diagnostics\DiagnosticResult;

final readonly class FixtureDiagnosticCheck implements DiagnosticCheck
{
    /** @param Closure(): DiagnosticResult $check */
    public function __construct(private string $name, private Closure $check) {}

    public function id(): string
    {
        return $this->name;
    }

    public function run(): DiagnosticResult
    {
        return ($this->check)();
    }
}
