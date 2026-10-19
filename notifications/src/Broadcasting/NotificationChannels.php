<?php

declare(strict_types=1);

namespace Gcego\Notifications\Broadcasting;

use Gcego\Notifications\Delivery\Recipients;
use Gcego\Notifications\NotificationsConfig;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model;

/**
 * Private broadcast channel names: "<prefix>.<hash>", where the hash is an
 * HMAC (keyed with the application key) of the recipient reference. Names
 * are not guessable and carry no user id; subscribing is authorized by
 * recomputing the hash for the authenticated user (NotificationChannelAuthorizer).
 */
final readonly class NotificationChannels
{
    public function __construct(private NotificationsConfig $config, private Repository $settings) {}

    public function for(Model $recipient): string
    {
        return $this->config->channel.'.'.$this->hash($recipient);
    }

    public function hash(Model $recipient): string
    {
        $key = $this->settings->get('app.key');

        return substr(hash_hmac('sha256', 'gcego-notifications|'.Recipients::reference($recipient), is_string($key) ? $key : ''), 0, 40);
    }

    /**
     * The channel pattern registered with Laravel broadcasting.
     */
    public function pattern(): string
    {
        return $this->config->channel.'.{hash}';
    }
}
