<?php

declare(strict_types=1);

namespace Gcego\Notifications;

use Gcego\Notifications\Contracts\NotificationDeliveryPolicy;
use Gcego\Notifications\Exceptions\InvalidNotificationException;

/**
 * Validated gcego-notifications configuration (a container singleton, plain data).
 */
final readonly class NotificationsConfig
{
    public const array Channels = ['toast', 'database', 'broadcast', 'mail'];

    public const array Positions = ['top-right', 'top-center', 'bottom-right', 'bottom-center'];

    /**
     * @param  list<string>  $defaultChannels
     * @param  list<int>  $backoff
     * @param  array<string, bool>  $preferenceDefaults
     * @param  array<string, array{label?: string, mandatory?: list<string>}>  $categories
     * @param  class-string<NotificationDeliveryPolicy>|null  $deliveryPolicy
     */
    public function __construct(
        public array $defaultChannels = ['database'],
        public bool $toasts = true,
        public string $position = 'top-right',
        public int $duration = 5000,
        public int $errorDuration = 8000,
        public int $maxVisible = 4,
        public bool $actionResultToasts = true,
        public bool $database = true,
        public int $perPage = 20,
        public int $recent = 8,
        public int $unreadCacheTtl = 0,
        public bool $deletable = true,
        public ?int $retentionDays = null,
        public bool $broadcasting = false,
        public string $channel = 'gcego.notifications',
        public int $pollSeconds = 0,
        public bool $mail = true,
        public bool $queue = true,
        public ?string $queueConnection = null,
        public ?string $queueName = 'notifications',
        public int $tries = 3,
        public array $backoff = [10, 60, 300],
        public bool $afterCommit = true,
        public int $maxRecipients = 1000,
        public string $localeAttribute = 'locale',
        public string $timezoneAttribute = 'timezone',
        public bool $preferences = true,
        public array $preferenceDefaults = ['database' => true, 'broadcast' => true, 'mail' => true],
        public array $categories = [],
        public bool $authorizeRecipients = true,
        public bool $enforceTenantScope = true,
        public ?string $deliveryPolicy = null,
        public bool $tracking = false,
        public bool $panels = true,
        public bool $panelFilter = false,
    ) {
        foreach ($defaultChannels as $channel) {
            if (! in_array($channel, self::Channels, true)) {
                throw InvalidNotificationException::invalid('gcego-notifications.default_channels', sprintf('"%s" is not a channel (use %s).', $channel, implode(', ', self::Channels)));
            }
        }

        if (! in_array($position, self::Positions, true)) {
            throw InvalidNotificationException::invalid('gcego-notifications.toasts.position', 'use one of '.implode(', ', self::Positions).'.');
        }

        foreach (['toasts.duration' => $duration, 'toasts.error_duration' => $errorDuration, 'database.unread_cache_ttl' => $unreadCacheTtl, 'broadcasting.poll_seconds' => $pollSeconds] as $key => $value) {
            if ($value < 0 || $value > 3_600_000) {
                throw InvalidNotificationException::invalid('gcego-notifications.'.$key, 'must be zero or a positive number.');
            }
        }

        if ($pollSeconds !== 0 && $pollSeconds < 10) {
            throw InvalidNotificationException::invalid('gcego-notifications.broadcasting.poll_seconds', 'poll at most every 10 seconds (or 0 for no polling).');
        }

        foreach (['toasts.max_visible' => [$maxVisible, 1, 10], 'database.per_page' => [$perPage, 1, 100], 'database.recent' => [$recent, 1, 50], 'queue.tries' => [$tries, 1, 20], 'recipients.max' => [$maxRecipients, 1, 10_000]] as $key => [$value, $min, $max]) {
            if ($value < $min || $value > $max) {
                throw InvalidNotificationException::invalid('gcego-notifications.'.$key, sprintf('must be between %d and %d.', $min, $max));
            }
        }

        if ($retentionDays !== null && $retentionDays < 1) {
            throw InvalidNotificationException::invalid('gcego-notifications.database.retention_days', 'must be at least 1, or null to keep notifications.');
        }

        if (preg_match('/^[a-z][a-z0-9_.-]{0,63}$/', $channel) !== 1) {
            throw InvalidNotificationException::invalid('gcego-notifications.broadcasting.channel', 'use a channel prefix such as "gcego.notifications".');
        }

        foreach ($categories as $id => $category) {
            if (preg_match(Definitions\NotificationCategory::IdPattern, $id) !== 1) {
                throw InvalidNotificationException::invalid('gcego-notifications.categories', sprintf('"%s" is not a category id (lowercase letters, digits, "-" and "_").', $id));
            }

            foreach ($category['mandatory'] ?? [] as $mandatory) {
                if (! in_array($mandatory, ['database', 'broadcast', 'mail'], true)) {
                    throw InvalidNotificationException::invalid('gcego-notifications.categories.'.$id.'.mandatory', sprintf('"%s" is not a database, broadcast or mail channel.', $mandatory));
                }
            }
        }

        if ($deliveryPolicy !== null && ! is_a($deliveryPolicy, NotificationDeliveryPolicy::class, true)) {
            throw InvalidNotificationException::invalid('gcego-notifications.security.delivery_policy', sprintf('%s does not implement %s.', $deliveryPolicy, NotificationDeliveryPolicy::class));
        }
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $read = static function (string $section, string $key, mixed $default) use ($config): mixed {
            $values = $config[$section] ?? [];

            return is_array($values) && array_key_exists($key, $values) ? $values[$key] : $default;
        };
        $bool = static fn (string $section, string $key, bool $default): bool => is_bool($value = $read($section, $key, $default)) ? $value : throw InvalidNotificationException::invalid("gcego-notifications.$section.$key", 'must be true or false.');
        $int = static fn (string $section, string $key, int $default): int => is_int($value = $read($section, $key, $default)) ? $value : throw InvalidNotificationException::invalid("gcego-notifications.$section.$key", 'must be an integer.');
        $string = static fn (string $section, string $key, string $default): string => is_string($value = $read($section, $key, $default)) ? $value : throw InvalidNotificationException::invalid("gcego-notifications.$section.$key", 'must be a string.');
        $nullable = static fn (string $section, string $key, ?string $default = null): ?string => is_string($value = $read($section, $key, $default)) || $value === null ? $value : throw InvalidNotificationException::invalid("gcego-notifications.$section.$key", 'must be a string or null.');
        $channels = $config['default_channels'] ?? ['database'];

        if (! is_array($channels) || array_filter($channels, static fn (mixed $channel): bool => ! is_string($channel)) !== []) {
            throw InvalidNotificationException::invalid('gcego-notifications.default_channels', 'must be a list of channel names.');
        }

        $backoff = $read('queue', 'backoff', [10, 60, 300]);

        if (! is_array($backoff) || array_filter($backoff, static fn (mixed $seconds): bool => ! is_int($seconds) || $seconds < 0) !== []) {
            throw InvalidNotificationException::invalid('gcego-notifications.queue.backoff', 'must be a list of seconds.');
        }

        $defaults = $read('preferences', 'defaults', ['database' => true, 'broadcast' => true, 'mail' => true]);

        if (! is_array($defaults) || array_filter($defaults, static fn (mixed $enabled, mixed $channel): bool => ! is_bool($enabled) || ! in_array($channel, ['database', 'broadcast', 'mail'], true), ARRAY_FILTER_USE_BOTH) !== []) {
            throw InvalidNotificationException::invalid('gcego-notifications.preferences.defaults', 'must map database, broadcast and mail to true or false.');
        }

        $categories = $config['categories'] ?? [];

        if (! is_array($categories)) {
            throw InvalidNotificationException::invalid('gcego-notifications.categories', 'must be an array of id => settings.');
        }

        $normalized = [];

        foreach ($categories as $id => $category) {
            if (! is_string($id) || ! is_array($category)) {
                throw InvalidNotificationException::invalid('gcego-notifications.categories', 'must be an array of id => settings.');
            }

            $label = $category['label'] ?? null;
            $mandatory = $category['mandatory'] ?? [];

            if (($label !== null && ! is_string($label)) || ! is_array($mandatory) || array_filter($mandatory, static fn (mixed $channel): bool => ! is_string($channel)) !== []) {
                throw InvalidNotificationException::invalid('gcego-notifications.categories.'.$id, 'use ["label" => string, "mandatory" => list of channels].');
            }

            /** @var list<string> $mandatory */
            $normalized[$id] = array_filter(['label' => $label, 'mandatory' => array_values($mandatory)], static fn (mixed $value): bool => $value !== null);
        }

        $policy = $nullable('security', 'delivery_policy');
        $retention = $read('database', 'retention_days', null);

        /** @var list<string> $channels */
        /** @var list<int> $backoff */
        /** @var array<string, bool> $defaults */
        return new self(
            defaultChannels: array_values($channels),
            toasts: $bool('toasts', 'enabled', true),
            position: $string('toasts', 'position', 'top-right'),
            duration: $int('toasts', 'duration', 5000),
            errorDuration: $int('toasts', 'error_duration', 8000),
            maxVisible: $int('toasts', 'max_visible', 4),
            actionResultToasts: $bool('toasts', 'action_results', true),
            database: $bool('database', 'enabled', true),
            perPage: $int('database', 'per_page', 20),
            recent: $int('database', 'recent', 8),
            unreadCacheTtl: $int('database', 'unread_cache_ttl', 0),
            deletable: $bool('database', 'deletable', true),
            retentionDays: is_int($retention) || $retention === null ? $retention : throw InvalidNotificationException::invalid('gcego-notifications.database.retention_days', 'must be a number of days or null.'),
            broadcasting: $bool('broadcasting', 'enabled', false),
            channel: $string('broadcasting', 'channel', 'gcego.notifications'),
            pollSeconds: $int('broadcasting', 'poll_seconds', 0),
            mail: $bool('mail', 'enabled', true),
            queue: $bool('queue', 'enabled', true),
            queueConnection: $nullable('queue', 'connection'),
            queueName: $nullable('queue', 'queue', 'notifications'),
            tries: $int('queue', 'tries', 3),
            backoff: array_values($backoff),
            afterCommit: $bool('queue', 'after_commit', true),
            maxRecipients: $int('recipients', 'max', 1000),
            localeAttribute: $string('recipients', 'locale_attribute', 'locale'),
            timezoneAttribute: $string('recipients', 'timezone_attribute', 'timezone'),
            preferences: $bool('preferences', 'enabled', true),
            preferenceDefaults: $defaults,
            categories: $normalized,
            authorizeRecipients: $bool('security', 'authorize_recipients', true),
            enforceTenantScope: $bool('security', 'enforce_tenant_scope', true),
            deliveryPolicy: $policy === null ? null : (is_a($policy, NotificationDeliveryPolicy::class, true) ? $policy : throw InvalidNotificationException::invalid('gcego-notifications.security.delivery_policy', sprintf('%s does not implement %s.', $policy, NotificationDeliveryPolicy::class))),
            tracking: $bool('tracking', 'enabled', false),
            panels: $bool('panels', 'enabled', true),
            panelFilter: $bool('panels', 'filter', false),
        );
    }

    public function durationFor(Definitions\NotificationType $type, Definitions\NotificationPriority $priority): int
    {
        if ($priority === Definitions\NotificationPriority::Critical) {
            return 0;
        }

        return $type === Definitions\NotificationType::Error ? $this->errorDuration : $this->duration;
    }
}
