<?php

declare(strict_types=1);

namespace Gcego\Security\Audit;

use Gcego\Security\Contracts\AuditReporter;

final class NullAuditReporter implements AuditReporter
{
    public function report(SecurityEvent $event): void {}
}
