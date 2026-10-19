<?php

declare(strict_types=1);

namespace Gcego\Notifications\Laravel;

use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The queued variant: Laravel dispatches one job per channel.
 */
final class QueuedDeliveredNotification extends DeliveredNotification implements ShouldQueue {}
