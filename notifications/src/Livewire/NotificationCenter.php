<?php

declare(strict_types=1);

namespace Gcego\Notifications\Livewire;

use Gcego\Notifications\Database\DatabaseNotificationRepository;
use Gcego\Notifications\Database\NotificationViewer;
use Gcego\Notifications\Database\ViewerResolver;
use Gcego\Notifications\NotificationsConfig;
use Gcego\Notifications\Rendering\NotificationStyles;
use Gcego\Notifications\Rendering\NotificationTime;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * <livewire:gcego-notification-center />: the bell, its unread badge and a
 * dropdown of recent notifications for the signed-in user. Mounting costs
 * one count query; the list loads when the dropdown first opens.
 *
 * Every id the browser sends is looked up inside the viewer's own scope
 * (DatabaseNotificationRepository), so it can only ever affect the
 * viewer's notifications. On panel pages, gcego/panels re-checks panel
 * access on every update.
 */
final class NotificationCenter extends Component
{
    /** The list was requested (lazy loading). */
    public bool $loaded = false;

    /** Where "View all" goes (set by trusted code, e.g. the panel extension). */
    #[Locked]
    public ?string $inboxUrl = null;

    public function mount(?string $inboxUrl = null): void
    {
        $this->inboxUrl = $inboxUrl !== null && str_starts_with($inboxUrl, '/') && ! str_starts_with($inboxUrl, '//') ? $inboxUrl : null;
    }

    public function load(): void
    {
        $this->loaded = true;
    }

    public function markAsRead(mixed $id): void
    {
        $viewer = $this->viewer();

        if ($viewer !== null && is_string($id)) {
            $this->repository()->markRead($viewer, [$id]);
        }
    }

    public function markAllAsRead(): void
    {
        $viewer = $this->viewer();

        if ($viewer !== null) {
            $this->repository()->markAllRead($viewer);
        }
    }

    /**
     * Follow a notification's link: mark it read, then go to the stored URL
     * (looked up again on the server; the browser only names the notification).
     */
    public function open(mixed $id, mixed $action = 0): void
    {
        $viewer = $this->viewer();

        if ($viewer === null || ! is_string($id) || ! is_int($action)) {
            return;
        }

        $item = $this->repository()->find($viewer, $id);
        $url = $item?->payload->actions[$action]['url'] ?? null;

        if ($item === null) {
            return;
        }

        $this->repository()->markRead($viewer, [$id]);

        if (is_string($url)) {
            $this->redirect($url);
        }
    }

    #[On('gcego-notifications-refresh')]
    public function refresh(): void
    {
        // Re-rendering re-reads the count (and the list once loaded).
    }

    public function render(): View
    {
        $viewer = $this->viewer();
        $config = app(NotificationsConfig::class);

        return view('gcego-notifications::center.center', [
            'viewer' => $viewer,
            'count' => $viewer === null ? 0 : $this->repository()->unreadCount($viewer),
            'items' => $viewer !== null && $this->loaded ? $this->repository()->recent($viewer) : null,
            'styles' => app(NotificationStyles::class),
            'time' => app(NotificationTime::class),
            'poll' => $config->pollSeconds,
            'broadcasting' => $config->broadcasting,
        ]);
    }

    private function viewer(): ?NotificationViewer
    {
        return app(ViewerResolver::class)->current();
    }

    private function repository(): DatabaseNotificationRepository
    {
        return app(DatabaseNotificationRepository::class);
    }
}
