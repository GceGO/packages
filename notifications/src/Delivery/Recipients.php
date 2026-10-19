<?php

declare(strict_types=1);

namespace Gcego\Notifications\Delivery;

use Gcego\Notifications\Exceptions\InvalidNotificationException;
use Gcego\Notifications\NotificationsConfig;
use Illuminate\Database\Eloquent\Model;

/**
 * Normalizes recipients: Eloquent models that receive Laravel
 * notifications (the Notifiable trait), each once, at most
 * gcego-notifications.recipients.max per call.
 */
final readonly class Recipients
{
    public function __construct(private NotificationsConfig $config) {}

    /**
     * @param  Model|iterable<mixed>  $recipients
     * @return list<Model>
     */
    public function normalize(Model|iterable $recipients): array
    {
        $unique = [];

        foreach ($recipients instanceof Model ? [$recipients] : $recipients as $recipient) {
            if (! $recipient instanceof Model || ! method_exists($recipient, 'routeNotificationFor') || $recipient->getKey() === null) {
                throw InvalidNotificationException::invalid('A recipient', 'recipients must be saved Eloquent models using Laravel\'s Notifiable trait.');
            }

            $unique[self::reference($recipient)] = $recipient;

            if (count($unique) > $this->config->maxRecipients) {
                throw InvalidNotificationException::invalid('The recipients', sprintf('send to at most %d recipients at once (gcego-notifications.recipients.max); chunk larger audiences.', $this->config->maxRecipients));
            }
        }

        return array_values($unique);
    }

    /**
     * "<morph class>:<key>": how notifications reference their recipient.
     */
    public static function reference(Model $recipient): string
    {
        $key = $recipient->getKey();

        return $recipient->getMorphClass().':'.(is_scalar($key) ? (string) $key : '');
    }
}
