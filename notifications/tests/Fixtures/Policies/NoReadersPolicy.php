<?php

declare(strict_types=1);

namespace Gcego\Notifications\Tests\Fixtures\Policies;

use Gcego\Notifications\Contracts\NotificationDeliveryPolicy;
use Gcego\Notifications\Definitions\NotificationPayload;
use Gcego\Notifications\Delivery\DeliveryContext;
use Illuminate\Database\Eloquent\Model;

/**
 * The application's rule: readers never receive "system" notifications.
 */
final class NoReadersPolicy implements NotificationDeliveryPolicy
{
    public function allows(Model $recipient, NotificationPayload $payload, DeliveryContext $context): bool
    {
        return ! ($recipient->getAttribute('name') === 'Reader' && $payload->category === 'system');
    }
}
