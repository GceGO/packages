<?php

declare(strict_types=1);

namespace Gcego\Security\Audit;

use Gcego\Security\Contracts\AuditReporter;
use Illuminate\Log\LogManager;
use Throwable;

/**
 * Writes security events to a Laravel log channel as structured context.
 * A failed write never breaks the request.
 */
final readonly class LogAuditReporter implements AuditReporter
{
    public function __construct(private LogManager $logs, private ?string $channel = null) {}

    public function report(SecurityEvent $event): void
    {
        try {
            $this->logs->channel($this->channel)->info($event->type->value, $event->toArray());
        } catch (Throwable) {
            // Auditing must never deny or break the operation it observes.
        }
    }
}
