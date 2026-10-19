<?php

declare(strict_types=1);

namespace Gcego\Notifications\Diagnostics;

use Gcego\Notifications\Broadcasting\NotificationChannels;
use Gcego\Notifications\Definitions\NotificationCategories;
use Gcego\Notifications\Integration\PanelNotificationsExtension;
use Gcego\Notifications\NotificationsConfig;
use Gcego\Panels\Extensions\PanelExtensions;
use Gcego\Panels\PanelsConfig;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Schema\Builder;
use Illuminate\Notifications\Notifiable;
use Laravel\Reverb\ReverbServiceProvider;
use Throwable;

/**
 * Checks what notifications need: the tables and columns, the queue,
 * the broadcaster and the private channel, mail, the user model and the
 * panel integration. It reads configuration and the schema only (never
 * notifications, recipients or credentials).
 */
final readonly class NotificationInspector
{
    public function __construct(
        private NotificationsConfig $config,
        private Repository $settings,
        private ConnectionResolverInterface $connections,
        private NotificationCategories $categories,
        private NotificationChannels $channels,
    ) {}

    public function inspect(): NotificationReport
    {
        $diagnostics = [];
        $connection = $this->connections->connection();
        $schema = $connection instanceof Connection ? $connection->getSchemaBuilder() : null;
        $has = static function (\Closure $check) use ($schema): bool {
            try {
                return $schema !== null && $check($schema) === true;
            } catch (Throwable) {
                return false;
            }
        };

        if ($this->config->database && ! $has(static fn (Builder $schema): bool => $schema->hasTable('notifications'))) {
            $diagnostics[] = NotificationDiagnostic::error('database.table', 'The "notifications" table is missing: run php artisan migrate.');
        } elseif ($this->config->database && ! $has(static fn (Builder $schema): bool => $schema->hasColumns('notifications', ['gcego_tenant', 'gcego_panel', 'gcego_category', 'gcego_key', 'gcego_expires_at']))) {
            $diagnostics[] = NotificationDiagnostic::error('database.columns', 'The gcego columns of "notifications" are missing: run php artisan migrate.');
        }

        if ($this->config->preferences && ! $has(static fn (Builder $schema): bool => $schema->hasTable('gcego_notification_preferences'))) {
            $diagnostics[] = NotificationDiagnostic::error('preferences.table', 'The "gcego_notification_preferences" table is missing: run php artisan migrate.');
        }

        if ($this->config->tracking && ! $has(static fn (Builder $schema): bool => $schema->hasTable('gcego_notification_deliveries'))) {
            $diagnostics[] = NotificationDiagnostic::error('tracking.table', 'Delivery tracking is enabled but "gcego_notification_deliveries" is missing: run php artisan migrate.');
        }

        $queue = $this->config->queueConnection ?? $this->settings->get('queue.default');

        if ($this->config->queue && $queue === 'sync') {
            $diagnostics[] = NotificationDiagnostic::warning('queue.sync', 'Delivery is queued on the "sync" connection, so it runs during the request; use a real queue and a worker in production.');
        }

        $broadcaster = $this->settings->get('broadcasting.default');

        if ($this->config->broadcasting) {
            if (in_array($broadcaster, [null, 'null', 'log'], true)) {
                $diagnostics[] = NotificationDiagnostic::error('broadcasting.driver', sprintf('Broadcasting is enabled but the broadcaster is "%s"; configure Laravel Reverb (BROADCAST_CONNECTION=reverb).', is_string($broadcaster) ? $broadcaster : 'null'));
            }

            if (! $this->channelRegistered()) {
                $diagnostics[] = NotificationDiagnostic::error('broadcasting.channel', sprintf('The private channel "%s" is not registered.', $this->channels->pattern()));
            }
        }

        if ($this->config->mail && in_array($this->settings->get('mail.default'), ['log', 'array'], true)) {
            $diagnostics[] = NotificationDiagnostic::warning('mail.driver', 'Mail is enabled but the mailer only logs messages.');
        }

        $model = $this->settings->get('auth.providers.users.model');

        if (is_string($model) && class_exists($model) && ! in_array(Notifiable::class, class_uses_recursive($model), true)) {
            $diagnostics[] = NotificationDiagnostic::error('recipients.notifiable', sprintf('%s does not use Laravel\'s Notifiable trait.', $model));
        }

        if ($this->config->panels && ! $this->panelsExtended()) {
            $diagnostics[] = NotificationDiagnostic::warning('panels.extension', 'The panel extension is not registered: panels show no bell or inbox.');
        }

        return new NotificationReport(
            channels: ['toast' => $this->config->toasts, 'database' => $this->config->database, 'broadcast' => $this->config->broadcasting, 'mail' => $this->config->mail],
            infrastructure: [
                'queue' => $this->config->queue ? (is_string($queue) ? $queue : null) : 'disabled',
                'queue_name' => $this->config->queueName,
                'after_commit' => $this->config->afterCommit,
                'broadcaster' => is_string($broadcaster) ? $broadcaster : null,
                'channel' => $this->channels->pattern(),
                'reverb' => class_exists(ReverbServiceProvider::class),
                'mailer' => is_string($mailer = $this->settings->get('mail.default')) ? $mailer : null,
                'tracking' => $this->config->tracking,
                'preferences' => $this->config->preferences,
            ],
            categories: array_keys($this->categories->all()),
            diagnostics: $diagnostics,
        );
    }

    private function channelRegistered(): bool
    {
        try {
            $broadcaster = app(BroadcastManager::class)->connection();

            return $broadcaster instanceof Broadcaster && $broadcaster->getChannels()->has($this->channels->pattern());
        } catch (Throwable) {
            return false;
        }
    }

    private function panelsExtended(): bool
    {
        try {
            return in_array(PanelNotificationsExtension::class, app(PanelExtensions::class)->classes(), true) || in_array(PanelNotificationsExtension::class, app(PanelsConfig::class)->extensions, true);
        } catch (Throwable) {
            return false;
        }
    }
}
