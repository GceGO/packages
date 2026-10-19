<?php

declare(strict_types=1);

namespace Gcego\Studio\Contracts;

use Gcego\Studio\Health\HealthCheckResult;

/**
 * A read-only health check (resolved from the container). It must not
 * write to the database, change configuration or expose secrets; an
 * exception it throws is reported as a failed check, never propagated.
 */
interface StudioHealthCheck
{
    /** A stable identifier such as "panels.access". */
    public function id(): string;

    /** Whether it belongs to the production readiness checks (gcego:studio:health --production). */
    public function production(): bool;

    public function run(): HealthCheckResult;
}
