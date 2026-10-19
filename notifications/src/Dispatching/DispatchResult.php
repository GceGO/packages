<?php

declare(strict_types=1);

namespace Gcego\Notifications\Dispatching;

use Gcego\Notifications\Exceptions\NotificationDeliveryException;

/**
 * What happened to a send(), per recipient and channel.
 */
final readonly class DispatchResult
{
    /**
     * @param  list<Delivery>  $deliveries
     */
    public function __construct(public array $deliveries = []) {}

    /**
     * @return list<Delivery>
     */
    public function with(DeliveryStatus $status): array
    {
        return array_values(array_filter($this->deliveries, static fn (Delivery $delivery): bool => $delivery->status === $status));
    }

    /**
     * @return list<Delivery>
     */
    public function failed(): array
    {
        return $this->with(DeliveryStatus::Failed);
    }

    /**
     * @return list<Delivery>
     */
    public function channel(string $channel): array
    {
        return array_values(array_filter($this->deliveries, static fn (Delivery $delivery): bool => $delivery->channel === $channel));
    }

    /**
     * Status => count.
     *
     * @return array<string, int>
     */
    public function summary(): array
    {
        $summary = [];

        foreach ($this->deliveries as $delivery) {
            $summary[$delivery->status->value] = ($summary[$delivery->status->value] ?? 0) + 1;
        }

        return $summary;
    }

    /**
     * No delivery failed (skipped ones are not failures).
     */
    public function successful(): bool
    {
        return $this->failed() === [];
    }

    /**
     * @throws NotificationDeliveryException
     */
    public function throw(): self
    {
        return $this->successful() ? $this : throw new NotificationDeliveryException($this);
    }

    public function merge(self $other): self
    {
        return new self([...$this->deliveries, ...$other->deliveries]);
    }
}
