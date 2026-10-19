<?php

declare(strict_types=1);

namespace Gcego\Notifications\Contracts;

use Gcego\Notifications\Definitions\NotificationPayload;
use Gcego\Notifications\Delivery\DeliveryContext;
use Illuminate\Database\Eloquent\Model;

/**
 * The application's own rule for who may receive what
 * (gcego-notifications.security.delivery_policy), checked for every
 * recipient after tenant membership and before preferences, at send time
 * and again when a queued delivery runs:
 *
 *     final class OnlyActiveAccounts implements NotificationDeliveryPolicy
 *     {
 *         public function allows(Model $recipient, NotificationPayload $payload, DeliveryContext $context): bool
 *         {
 *             return $recipient->getAttribute('suspended_at') === null;
 *         }
 *     }
 *
 * Returning false (or throwing) skips the recipient.
 */
interface NotificationDeliveryPolicy
{
    public function allows(Model $recipient, NotificationPayload $payload, DeliveryContext $context): bool;
}
