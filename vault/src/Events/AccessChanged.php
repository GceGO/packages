<?php

declare(strict_types=1);

namespace Gcego\Vault\Events;

use Gcego\Vault\Enums\AuditEvent;
use Gcego\Vault\Support\Holder;

/**
 * Dispatched after every audited Vault change (role, permission, assignment,
 * grant, expiry, access request), for listeners such as notifications.
 * Carries identifiers and names only.
 */
final readonly class AccessChanged
{
    /**
     * @param  array<string, scalar|list<scalar>|null>  $payload
     */
    public function __construct(
        public AuditEvent $event,
        public ?Holder $actor,
        public ?Holder $subject,
        public ?string $target,
        public string $tenant,
        public ?string $reason,
        public array $payload,
    ) {}
}
