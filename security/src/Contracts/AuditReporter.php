<?php

declare(strict_types=1);

namespace Gcego\Security\Contracts;

use Gcego\Security\Audit\SecurityEvent;

/**
 * Receives security events (denials, tenant denials, sensitive grants).
 * Implementations must not throw and must not block the request.
 */
interface AuditReporter
{
    public function report(SecurityEvent $event): void;
}
