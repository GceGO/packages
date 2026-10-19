<?php

declare(strict_types=1);

namespace Gcego\Notifications\Contracts;

use Gcego\Notifications\Dispatching\Delivery;

/**
 * Records delivery outcomes (gcego-notifications.tracking). The default
 * implementation stores them in gcego_notification_deliveries when tracking
 * is enabled; bind another one to send them elsewhere (an audit log).
 */
interface NotificationDeliveryTracker
{
    public function record(Delivery $delivery): void;
}
