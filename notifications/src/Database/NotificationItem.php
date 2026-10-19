<?php

declare(strict_types=1);

namespace Gcego\Notifications\Database;

use Carbon\CarbonImmutable;
use Gcego\Notifications\Definitions\NotificationPayload;

/**
 * A stored notification as the viewer sees it.
 */
final readonly class NotificationItem
{
    public function __construct(
        public string $id,
        public NotificationPayload $payload,
        public bool $read,
        public CarbonImmutable $createdAt,
    ) {}
}
