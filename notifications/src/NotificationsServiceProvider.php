<?php

declare(strict_types=1);

namespace Gcego\Notifications;

use Gcego\Chroma\ChromaManager;
use Gcego\Chroma\Slots\SlotDefinition;
use Gcego\Core\Contracts\PackageInterface;
use Gcego\Core\Packages\PackageDescriptor;
use Gcego\Notifications\Broadcasting\NotificationChannelAuthorizer;
use Gcego\Notifications\Broadcasting\NotificationChannels;
use Gcego\Notifications\Console\PruneNotificationsCommand;
use Gcego\Notifications\Contracts\NotificationDeliveryTracker;
use Gcego\Notifications\Database\DatabaseNotificationRepository;
use Gcego\Notifications\Database\UnreadCounts;
use Gcego\Notifications\Database\ViewerResolver;
use Gcego\Notifications\Definitions\NotificationCategories;
use Gcego\Notifications\Delivery\DatabaseDeliveryTracker;
use Gcego\Notifications\Delivery\RecipientAuthorizer;
use Gcego\Notifications\Delivery\Recipients;
use Gcego\Notifications\Diagnostics\NotificationInspector;
use Gcego\Notifications\Dispatching\Delivery;
use Gcego\Notifications\Dispatching\DeliveryStatus;
use Gcego\Notifications\Dispatching\NotificationDispatcher;
use Gcego\Notifications\Integration\PanelNotificationsExtension;
use Gcego\Notifications\Laravel\DeliveredNotification;
use Gcego\Notifications\Laravel\QueuedDeliveredNotification;
use Gcego\Notifications\Livewire\NotificationCenter;
use Gcego\Notifications\Livewire\NotificationInbox;
use Gcego\Notifications\Livewire\NotificationPreferences;
use Gcego\Notifications\Livewire\ToastHost;
use Gcego\Notifications\Livewire\ToastHostRegistry;
use Gcego\Notifications\Preferences\NotificationPreferenceManager;
use Gcego\Notifications\Preferences\NotificationPreferenceRepository;
use Gcego\Notifications\Rendering\NotificationStyles;
use Gcego\Notifications\Rendering\NotificationTime;
use Gcego\Notifications\Toasts\ToastQueue;
use Gcego\Notifications\Toasts\ToastResponseHook;
use Gcego\Panels\PanelManager;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

use function Livewire\on;

/**
 * Laravel integration for gcego/notifications; also the "gcego/notifications" GCEGO package.
 *
 * register(): configuration and services (no queries, no configuration
 * reads). boot(): views, translations, migrations, Livewire components
 * (gcego-toasts, gcego-notification-center, gcego-notification-inbox,
 * gcego-notification-preferences), the Livewire response hook that
 * delivers toasts, styling slots and icons, delivery tracking of queued
 * notifications, the panel extension, the prune command and, once the
 * application has booted and only when broadcasting is enabled, the
 * private channel authorization.
 */
final class NotificationsServiceProvider extends ServiceProvider implements PackageInterface
{
    public const string Name = 'gcego-notifications';

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/gcego-notifications.php', self::Name);

        $this->app->singleton(NotificationsConfig::class, static function (Application $app): NotificationsConfig {
            $config = $app->make(Repository::class)->get(self::Name, []);

            return NotificationsConfig::fromArray(is_array($config) ? $config : []);
        });

        foreach ([NotificationManager::class, NotificationCategories::class, Recipients::class, NotificationChannels::class, NotificationChannelAuthorizer::class, UnreadCounts::class, DatabaseNotificationRepository::class, NotificationPreferenceRepository::class, ToastQueue::class, ToastResponseHook::class, NotificationStyles::class, NotificationTime::class, NotificationInspector::class] as $singleton) {
            $this->app->singleton($singleton);
        }

        // Per request (or queue job): they read the current user, tenant and panel, or remember per-request state.
        foreach ([NotificationPreferenceManager::class, ToastHostRegistry::class, ViewerResolver::class, RecipientAuthorizer::class, NotificationDispatcher::class] as $scoped) {
            $this->app->scoped($scoped);
        }

        $this->app->bindIf(NotificationDeliveryTracker::class, DatabaseDeliveryTracker::class, shared: true);
    }

    public function boot(): void
    {
        $root = dirname(__DIR__);
        $this->loadViewsFrom($root.'/resources/views', self::Name);
        $this->loadTranslationsFrom($root.'/resources/lang', self::Name);
        $this->loadMigrationsFrom($root.'/database/migrations');
        $this->publishes([$root.'/config/gcego-notifications.php' => $this->app->configPath('gcego-notifications.php')], 'gcego-notifications-config');
        $this->publishes([$root.'/database/migrations' => $this->app->databasePath('migrations')], 'gcego-notifications-migrations');
        $this->publishes([$root.'/resources/views' => $this->app->resourcePath('views/vendor/gcego-notifications')], 'gcego-notifications-views');

        Livewire::component('gcego-toasts', ToastHost::class);
        Livewire::component('gcego-notification-center', NotificationCenter::class);
        Livewire::component('gcego-notification-inbox', NotificationInbox::class);
        Livewire::component('gcego-notification-preferences', NotificationPreferences::class);
        on('response', fn (): \Closure => fn (array $payload): array => $this->app->make(ToastResponseHook::class)($payload));

        $this->defineSlots();
        $this->trackQueuedDeliveries();

        if ($this->app->bound(PanelManager::class)) {
            $this->app->make(PanelManager::class)->extend(PanelNotificationsExtension::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([PruneNotificationsCommand::class]);
        }

        $this->app->booted(function (): void {
            if ($this->app->make(NotificationsConfig::class)->broadcasting) {
                Broadcast::channel($this->app->make(NotificationChannels::class)->pattern(), NotificationChannelAuthorizer::class);
            }
        });
    }

    public function descriptor(): PackageDescriptor
    {
        return new PackageDescriptor(
            'gcego/notifications',
            requires: ['gcego/core', 'gcego/registry', 'gcego/runtime', 'gcego/chroma', 'gcego/data', 'gcego/security', 'gcego/actions', 'gcego/pages', 'gcego/panels'],
            capabilities: ['notifications', 'notifications.toasts', 'notifications.database', 'notifications.broadcast', 'notifications.mail', 'notifications.preferences', 'notifications.panels'],
            description: 'Toasts, a database inbox and notification center, private real-time broadcasting, mail, preferences and delivery tracking on Laravel Notifications.',
        );
    }

    private function defineSlots(): void
    {
        $slots = [];

        foreach (Notification::styleSlots() as $slot => $description) {
            $slots[] = new SlotDefinition($slot, description: $description);
        }

        $this->app->make(ChromaManager::class)->defineSlots(Notification::class, $slots, inherit: true);
    }

    /**
     * Queued deliveries were recorded as "queued"; record them as "sent" once Laravel's channel accepted them.
     */
    private function trackQueuedDeliveries(): void
    {
        $this->app->make(Dispatcher::class)->listen(NotificationSent::class, function (NotificationSent $event): void {
            if (! $event->notification instanceof QueuedDeliveredNotification) {
                return;
            }

            $this->app->make(NotificationDeliveryTracker::class)->record(new Delivery(
                (string) $event->notification->id,
                $event->notifiable instanceof Model ? Recipients::reference($event->notifiable) : null,
                DeliveredNotification::channelName($event->channel),
                DeliveryStatus::Sent,
            ));
        });
    }
}
