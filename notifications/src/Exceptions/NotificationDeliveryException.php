<?php

declare(strict_types=1);

namespace Gcego\Notifications\Exceptions;

use Gcego\Notifications\Dispatching\DispatchResult;
use RuntimeException;

/**
 * Delivery failed for at least one recipient and channel (DispatchResult::throw()).
 */
final class NotificationDeliveryException extends RuntimeException implements NotificationException
{
    public function __construct(public readonly DispatchResult $result)
    {
        parent::__construct(sprintf('%d notification deliveries failed.', count($result->failed())));
    }
}
