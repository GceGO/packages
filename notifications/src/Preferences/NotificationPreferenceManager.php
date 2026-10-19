<?php

declare(strict_types=1);

namespace Gcego\Notifications\Preferences;

use Gcego\Notifications\Definitions\NotificationCategories;
use Gcego\Notifications\Delivery\Recipients;
use Gcego\Notifications\Exceptions\InvalidNotificationException;
use Gcego\Notifications\NotificationsConfig;
use Illuminate\Database\Eloquent\Model;

/**
 * Decides whether a recipient wants a channel for a category, in a tenant.
 *
 * Precedence: a category's mandatory channels (trusted configuration) are
 * always on; otherwise the most specific stored preference wins (tenant +
 * category, tenant, category, all), then gcego-notifications.preferences.defaults.
 * Toasts are not preferences: they answer the user's own actions.
 *
 * Request-scoped: preferences are read once per recipient per request
 * (or queue job) and never shared between users.
 */
final class NotificationPreferenceManager
{
    public const array Channels = ['database', 'broadcast', 'mail'];

    /** @var array<string, list<NotificationPreference>> */
    private array $loaded = [];

    public function __construct(
        private readonly NotificationPreferenceRepository $repository,
        private readonly NotificationCategories $categories,
        private readonly NotificationsConfig $config,
    ) {}

    public function allows(Model $recipient, string $category, string $channel, ?string $tenant = null): bool
    {
        if ($channel === 'toast') {
            return true;
        }

        if ($this->categories->has($category) && $this->categories->get($category)->isMandatory($channel)) {
            return true;
        }

        if (! $this->config->preferences) {
            return $this->config->preferenceDefaults[$channel] ?? true;
        }

        $best = null;

        foreach ($this->preferences($recipient) as $preference) {
            $matches = $preference->channel === $channel
                && ($preference->category === NotificationPreference::AnyCategory || $preference->category === $category)
                && ($preference->tenant === NotificationPreference::AnyTenant || $preference->tenant === $tenant);

            if ($matches && ($best === null || $preference->specificity() > $best->specificity())) {
                $best = $preference;
            }
        }

        return $best !== null ? $best->enabled : ($this->config->preferenceDefaults[$channel] ?? true);
    }

    /**
     * Store a choice for a recipient (trusted code: the preferences component passes the signed-in user).
     */
    public function set(Model $recipient, string $channel, bool $enabled, string $category = NotificationPreference::AnyCategory, ?string $tenant = null): void
    {
        if (! in_array($channel, self::Channels, true)) {
            throw InvalidNotificationException::invalid('Channel "'.mb_substr($channel, 0, 40).'"', 'preferences cover database, broadcast and mail.');
        }

        if ($category !== NotificationPreference::AnyCategory && ! $this->categories->has($category)) {
            throw InvalidNotificationException::invalid('Category "'.mb_substr($category, 0, 48).'"', 'it is not registered.');
        }

        $this->repository->save($recipient, new NotificationPreference($category, $channel, $enabled, $tenant ?? NotificationPreference::AnyTenant));
        unset($this->loaded[Recipients::reference($recipient)]);
    }

    /**
     * Whether the user may change this channel for this category (mandatory channels may not).
     */
    public function changeable(string $category, string $channel): bool
    {
        return ! ($this->categories->has($category) && $this->categories->get($category)->isMandatory($channel));
    }

    /**
     * @return list<NotificationPreference>
     */
    public function preferences(Model $recipient): array
    {
        return $this->loaded[Recipients::reference($recipient)] ??= $this->repository->for($recipient);
    }
}
