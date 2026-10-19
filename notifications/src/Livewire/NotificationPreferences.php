<?php

declare(strict_types=1);

namespace Gcego\Notifications\Livewire;

use Gcego\Notifications\Database\ViewerResolver;
use Gcego\Notifications\Definitions\NotificationCategories;
use Gcego\Notifications\NotificationsConfig;
use Gcego\Notifications\Preferences\NotificationPreference;
use Gcego\Notifications\Preferences\NotificationPreferenceManager;
use Gcego\Notifications\Rendering\NotificationStyles;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * <livewire:gcego-notification-preferences />: the signed-in user's own
 * choices per category and channel. It only ever writes the current
 * user's preferences; mandatory channels cannot be turned off.
 */
final class NotificationPreferences extends Component
{
    public function toggle(mixed $category, mixed $channel): void
    {
        $viewer = app(ViewerResolver::class)->require();
        $preferences = app(NotificationPreferenceManager::class);
        $categories = app(NotificationCategories::class);

        if (! is_string($category) || ! is_string($channel) || ! $categories->has($category) || ! in_array($channel, $this->channels(), true) || ! $preferences->changeable($category, $channel)) {
            return;
        }

        $preferences->set($viewer->user, $channel, ! $preferences->allows($viewer->user, $category, $channel), $category);
    }

    public function render(): View
    {
        $viewer = app(ViewerResolver::class)->require();

        return view('gcego-notifications::preferences.preferences', [
            'viewer' => $viewer,
            'categories' => app(NotificationCategories::class)->all(),
            'channels' => $this->channels(),
            'preferences' => app(NotificationPreferenceManager::class),
            'styles' => app(NotificationStyles::class),
            'any' => NotificationPreference::AnyTenant,
        ]);
    }

    /**
     * @return list<string>
     */
    private function channels(): array
    {
        $config = app(NotificationsConfig::class);

        return array_keys(array_filter(['database' => $config->database, 'mail' => $config->mail, 'broadcast' => $config->broadcasting]));
    }
}
