<?php

declare(strict_types=1);

namespace Gcego\Cli\Contracts;

use Gcego\Cli\Diagnostics\DiagnosticResult;

/**
 * A check run by gcego:doctor. Checks are read-only: they never migrate,
 * clear caches, write files or create users. An exception is reported as
 * a failure of this check; the other checks still run.
 */
interface DiagnosticCheck
{
    /**
     * Stable identifier ("acme.queue").
     */
    public function id(): string;

    public function label(): string;

    /**
     * Whether the check needs --database (it queries the database).
     */
    public function usesDatabase(): bool;

    public function run(): DiagnosticResult;
}
