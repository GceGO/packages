<?php

declare(strict_types=1);

namespace Gcego\Notifications\Broadcasting;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Authorizes subscriptions to "<prefix>.{hash}" (Laravel broadcasting
 * channel class): only the authenticated user whose own hash it is may
 * join. The browser's channel name proves nothing by itself; guests and
 * other users are refused.
 */
final readonly class NotificationChannelAuthorizer
{
    public function __construct(private NotificationChannels $channels) {}

    public function join(?Authenticatable $user, string $hash): bool
    {
        return $user instanceof Model
            && preg_match('/^[a-f0-9]{40}$/', $hash) === 1
            && hash_equals($this->channels->hash($user), $hash);
    }
}
