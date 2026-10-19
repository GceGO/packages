<?php

declare(strict_types=1);

namespace Gcego\Notifications\Livewire;

use Gcego\Notifications\Database\DatabaseNotificationRepository;
use Gcego\Notifications\Database\NotificationQuery;
use Gcego\Notifications\Database\NotificationViewer;
use Gcego\Notifications\Database\ViewerResolver;
use Gcego\Notifications\Definitions\NotificationCategories;
use Gcego\Notifications\NotificationsConfig;
use Gcego\Notifications\Rendering\NotificationStyles;
use Gcego\Notifications\Rendering\NotificationTime;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * <livewire:gcego-notification-inbox />: all of the signed-in user's
 * notifications, filtered (status, category, dates) and paginated on the
 * server, with bulk mark-as-read/unread and delete.
 *
 * Filters and selections are untrusted input: filters are validated
 * (NotificationQuery) and selected ids only ever act inside the viewer's
 * own scope, at most 100 at a time.
 */
final class NotificationInbox extends Component
{
    use WithPagination;

    #[Url(except: 'all')]
    public string $status = 'all';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $until = '';

    /** @var list<string> */
    public array $selected = [];

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'category', 'from', 'until'], true)) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    public function markAsRead(mixed $id = null): void
    {
        $this->act(fn (NotificationViewer $viewer, array $ids): int => $this->repository()->markRead($viewer, $ids), $id);
    }

    public function markAsUnread(mixed $id = null): void
    {
        $this->act(fn (NotificationViewer $viewer, array $ids): int => $this->repository()->markUnread($viewer, $ids), $id);
    }

    public function delete(): void
    {
        $this->act(fn (NotificationViewer $viewer, array $ids): int => $this->repository()->delete($viewer, $ids));
    }

    public function markAllAsRead(): void
    {
        $viewer = app(ViewerResolver::class)->require();
        $this->repository()->markAllRead($viewer);
        $this->dispatch('gcego-notifications-refresh');
    }

    public function open(mixed $id, mixed $action = 0): void
    {
        $viewer = app(ViewerResolver::class)->require();

        if (! is_string($id) || ! is_int($action)) {
            return;
        }

        $item = $this->repository()->find($viewer, $id);

        if ($item === null) {
            return;
        }

        $this->repository()->markRead($viewer, [$id]);
        $url = $item->payload->actions[$action]['url'] ?? null;

        if (is_string($url)) {
            $this->redirect($url);
        }
    }

    #[On('gcego-notifications-refresh')]
    public function refresh(): void {}

    public function render(): View
    {
        $viewer = app(ViewerResolver::class)->require();
        $query = NotificationQuery::fromInput($this->status, $this->category, $this->from, $this->until);
        $config = app(NotificationsConfig::class);

        return view('gcego-notifications::inbox.inbox', [
            'viewer' => $viewer,
            'page' => $this->repository()->page($viewer, $query, is_int($page = $this->getPage()) ? $page : 1),
            'categories' => app(NotificationCategories::class)->all(),
            'deletable' => $config->deletable,
            'styles' => app(NotificationStyles::class),
            'time' => app(NotificationTime::class),
        ]);
    }

    /**
     * @param  \Closure(NotificationViewer, list<string>): int  $change
     */
    private function act(\Closure $change, mixed $id = null): void
    {
        $viewer = app(ViewerResolver::class)->require();
        $ids = is_string($id) ? [$id] : array_values(array_filter($this->selected, is_string(...)));
        $change($viewer, array_slice($ids, 0, DatabaseNotificationRepository::MaxBulk));

        if (! is_string($id)) {
            $this->selected = [];
        }

        $this->dispatch('gcego-notifications-refresh');
    }

    private function repository(): DatabaseNotificationRepository
    {
        return app(DatabaseNotificationRepository::class);
    }
}
