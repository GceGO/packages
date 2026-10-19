<?php

declare(strict_types=1);

namespace Gcego\Testing\Testables;

use Gcego\Notifications\Broadcasting\NotificationChannelAuthorizer;
use Gcego\Notifications\Broadcasting\NotificationChannels;
use Gcego\Notifications\Database\DatabaseNotificationRepository;
use Gcego\Notifications\Database\NotificationItem;
use Gcego\Notifications\Database\NotificationViewer;
use Gcego\Testing\Assertions\Verifier;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * A user's database notifications as gcego/notifications shows them in a
 * tenant (and panel): the same scoped repository the inbox and the
 * notification center use.
 */
final class InboxTestable extends Testable
{
    public function __construct(Verifier $verifier, private readonly Container $container, private readonly Model $user, private readonly ?string $tenant = null, private readonly ?string $panel = null)
    {
        parent::__construct($verifier);
    }

    /**
     * @return list<string>
     */
    public function titles(): array
    {
        return array_map(static fn (NotificationItem $item): string => $item->payload->displayTitle(), $this->repository()->recent($this->viewer(), 100));
    }

    public function assertUnread(int $count): self
    {
        $actual = $this->repository()->unreadCount($this->viewer());

        return $this->check($actual === $count, sprintf('assertUnread(%d)', $count), sprintf('Expected %d unread notification(s).', $count), ['unread' => $count], ['unread' => $actual]);
    }

    public function assertContains(string $title): self
    {
        return $this->check(in_array($title, $this->titles(), true), sprintf('assertContains(%s)', $this->export($title)), 'Expected the inbox to contain the notification.', ['title' => $title], ['titles' => $this->titles()]);
    }

    /**
     * Not visible to this user in this tenant: the isolation check for
     * another tenant's or another user's notification.
     */
    public function assertMissing(string $title): self
    {
        return $this->check(! in_array($title, $this->titles(), true), sprintf('assertMissing(%s)', $this->export($title)), 'Expected the inbox not to contain the notification.', ['title' => '(absent)'], ['titles' => $this->titles()]);
    }

    public function assertCannotFind(string $id): self
    {
        return $this->check($this->repository()->find($this->viewer(), $id) === null, sprintf('assertCannotFind(%s)', $this->export($id)), 'Expected the notification to be invisible to this viewer.', ['found' => false], ['found' => true]);
    }

    /**
     * Only the recipient may join their private broadcast channel.
     */
    public function assertChannelAuthorized(?Authenticatable $subscriber, bool $authorized = true): self
    {
        $hash = $this->container->make(NotificationChannels::class)->hash($this->user);
        $actual = $this->container->make(NotificationChannelAuthorizer::class)->join($subscriber, $hash);

        return $this->check($actual === $authorized, 'assertChannelAuthorized('.$this->export($authorized).')', $authorized ? 'Expected the subscriber to join the channel.' : 'Expected the subscriber to be refused the channel.', ['authorized' => $authorized], ['authorized' => $actual]);
    }

    protected function subject(): string
    {
        return 'notifications';
    }

    protected function context(): array
    {
        return ['User' => $this->user->getKey(), 'Tenant' => $this->tenant ?? '(none)', 'Panel' => $this->panel ?? '(any)'];
    }

    private function viewer(): NotificationViewer
    {
        return new NotificationViewer($this->user, $this->tenant, $this->panel);
    }

    private function repository(): DatabaseNotificationRepository
    {
        return $this->container->make(DatabaseNotificationRepository::class);
    }
}
