<?php

declare(strict_types=1);

namespace Gcego\Notifications\Dispatching;

use DateTimeInterface;
use Gcego\Notifications\Exceptions\InvalidNotificationException;

/**
 * How a notification is delivered (not what it says). Nulls fall back to
 * gcego-notifications configuration and the current request:
 *
 * - tenant: null uses the current tenant (gcego/security), if any; set
 *   $global for a notification that belongs to no tenant;
 * - panel: the gcego/panels panel it belongs to (for panel filtering);
 * - queue / connection / queueName / delay: Laravel queueing (delay = sendAt);
 * - afterCommit: deliver only once the surrounding transaction commits;
 * - idempotencyKey: at most one delivery per recipient and key;
 * - expiresAt: hidden afterwards, and not delivered if it is reached first.
 */
final readonly class DeliveryOptions
{
    public const string KeyPattern = '/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,119}$/';

    public function __construct(
        public ?string $tenant = null,
        public bool $global = false,
        public ?string $panel = null,
        public ?bool $queue = null,
        public ?string $connection = null,
        public ?string $queueName = null,
        public ?DateTimeInterface $delay = null,
        public ?bool $afterCommit = null,
        public ?string $idempotencyKey = null,
        public ?DateTimeInterface $expiresAt = null,
    ) {
        if ($idempotencyKey !== null && preg_match(self::KeyPattern, $idempotencyKey) !== 1) {
            throw InvalidNotificationException::invalid('The idempotency key', 'use up to 120 letters, digits, ".", "_", ":" and "-".');
        }

        if ($tenant !== null && ($global || preg_match('/^[A-Za-z0-9_.:-]{1,100}$/', $tenant) !== 1)) {
            throw InvalidNotificationException::invalid('The tenant', $global ? 'a global notification has no tenant.' : 'use a tenant key.');
        }

        if ($panel !== null && preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $panel) !== 1) {
            throw InvalidNotificationException::invalid('The panel', 'use a panel name such as "admin".');
        }

        if ($delay !== null && $expiresAt !== null && $expiresAt <= $delay) {
            throw InvalidNotificationException::invalid('The expiry', 'it must be after the scheduled delivery.');
        }
    }
}
