<?php

declare(strict_types=1);

namespace Gcego\Notifications\Dispatching;

/**
 * Where one delivery (one recipient, one channel) stands. "Sent" means
 * handed to the channel (stored, broadcast, given to the mailer); it is
 * not a confirmation that a person received it, and "Queued" only means a
 * job was accepted by the queue.
 */
enum DeliveryStatus: string
{
    /** Waiting for the surrounding database transaction to commit. */
    case Pending = 'pending';

    case Queued = 'queued';

    case Sent = 'sent';

    case Failed = 'failed';

    /** Not attempted: preferences, policy, tenant, expiry, duplicate or a disabled channel (see the reason). */
    case Skipped = 'skipped';
}
