<?php

declare(strict_types=1);

namespace Gcego\Studio\Tests\Fixtures\Extensions;

use Gcego\Studio\Contracts\StudioHealthCheck;
use Gcego\Studio\Health\HealthCheckResult;

final class ReportsHealthCheck implements StudioHealthCheck
{
    public static bool $explode = false;

    public function id(): string
    {
        return 'acme.reports';
    }

    public function production(): bool
    {
        return false;
    }

    public function run(): HealthCheckResult
    {
        if (self::$explode) {
            throw new \RuntimeException('The reports service is unreachable.');
        }

        return HealthCheckResult::pass($this->id(), 'Reports', 'Reports are ready.', 'acme/reports');
    }
}
