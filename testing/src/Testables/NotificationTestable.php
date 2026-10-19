<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Gcego\Notifications\Delivery\Recipients;
use Gcego\Notifications\Dispatching\Delivery;
use Gcego\Notifications\Dispatching\DeliveryStatus;
use Gcego\Notifications\Dispatching\DispatchResult;
use Gcego\Testing\Assertions\Verifier;
use Illuminate\Database\Eloquent\Model;

/**
 * What gcego/notifications did with one send: a delivery per recipient and
 * channel, each sent, queued, failed or skipped (with a reason such as
 * "tenant", "policy", "preference", "duplicate" or "expired").
 */
final class NotificationTestable extends Testable
{
    public function __construct(Verifier $verifier, private readonly DispatchResult $result)
    {
        parent::__construct($verifier);
    }

    public function result(): DispatchResult
    {
        return $this->result;
    }

    public function assertSent(?string $channel = null, string|Model|null $recipient = null): self
    {
        return $this->assertDelivered(DeliveryStatus::Sent, $channel, $recipient);
    }

    public function assertQueued(?string $channel = null, string|Model|null $recipient = null): self
    {
        return $this->assertDelivered(DeliveryStatus::Queued, $channel, $recipient);
    }

    public function assertSkipped(?string $reason = null, ?string $channel = null, string|Model|null $recipient = null): self
    {
        $matching = $this->matching(DeliveryStatus::Skipped, $channel, $recipient, $reason);

        return $this->check($matching !== [], sprintf('assertSkipped(%s, %s)', $this->export($reason), $this->export($channel)), 'Expected a skipped delivery'.($reason !== null ? " ($reason)" : '').'.', ['status' => 'skipped', 'reason' => $reason, 'channel' => $channel], ['deliveries' => $this->deliveries()]);
    }

    public function assertNothingDelivered(): self
    {
        $delivered = array_merge($this->result->with(DeliveryStatus::Sent), $this->result->with(DeliveryStatus::Queued));

        return $this->check($delivered === [], 'assertNothingDelivered()', 'Expected nothing to be sent or queued.', ['delivered' => 0], ['deliveries' => $this->deliveries()]);
    }

    public function assertNoFailures(): self
    {
        return $this->check($this->result->failed() === [], 'assertNoFailures()', 'Expected no failed delivery.', ['failed' => 0], ['deliveries' => $this->deliveries()]);
    }

    public function assertDeliveryCount(int $count): self
    {
        return $this->check(count($this->result->deliveries) === $count, sprintf('assertDeliveryCount(%d)', $count), sprintf('Expected %d delivery record(s).', $count), ['deliveries' => $count], ['deliveries' => $this->deliveries()]);
    }

    protected function subject(): string
    {
        return 'notification';
    }

    private function assertDelivered(DeliveryStatus $status, ?string $channel, string|Model|null $recipient): self
    {
        $matching = $this->matching($status, $channel, $recipient);

        return $this->check($matching !== [], sprintf('assert%s(%s)', ucfirst($status->value), $this->export($channel)), sprintf('Expected a %s delivery%s.', $status->value, $channel !== null ? " on $channel" : ''), ['status' => $status->value, 'channel' => $channel], ['deliveries' => $this->deliveries()]);
    }

    /**
     * @return list<Delivery>
     */
    private function matching(DeliveryStatus $status, ?string $channel, string|Model|null $recipient, ?string $reason = null): array
    {
        $reference = $recipient instanceof Model ? Recipients::reference($recipient) : $recipient;

        return array_values(array_filter($this->result->with($status), static fn (Delivery $delivery): bool => ($channel === null || $delivery->channel === $channel) && ($reference === null || $delivery->recipient === $reference) && ($reason === null || $delivery->reason === $reason)));
    }

    /**
     * @return list<string>
     */
    private function deliveries(): array
    {
        return array_map(static fn (Delivery $delivery): string => sprintf('%s → %s: %s%s', $delivery->channel, $delivery->recipient ?? '(session)', $delivery->status->value, $delivery->reason !== null ? ' ('.$delivery->reason.')' : ''), $this->result->deliveries);
    }
}
