<?php

declare(strict_types=1);

namespace Gcego\Notifications\Dispatching;

/**
 * One recipient on one channel. $recipient is an internal reference
 * ("<morph class>:<key>") for the sender's logs; it is never shown to
 * other recipients.
 */
final readonly class Delivery
{
    public function __construct(
        public string $notification,
        public ?string $recipient,
        public string $channel,
        public DeliveryStatus $status,
        public ?string $reason = null,
    ) {}

    /**
     * @return array{notification: string, recipient: string|null, channel: string, status: string, reason: string|null}
     */
    public function toArray(): array
    {
        return ['notification' => $this->notification, 'recipient' => $this->recipient, 'channel' => $this->channel, 'status' => $this->status->value, 'reason' => $this->reason];
    }
}
