<?php

declare(strict_types=1);

namespace Gcego\Notifications\Definitions;

use Gcego\Notifications\Exceptions\InvalidNotificationException;
use Gcego\Notifications\NotificationsConfig;

/**
 * The known categories: the generic built-ins ("general", "system",
 * "account", "security"), gcego-notifications.categories and those
 * registered at boot. Unknown categories are refused when sending.
 */
final class NotificationCategories
{
    public const string Default = 'general';

    /** @var array<string, NotificationCategory> */
    private array $categories = [];

    public function __construct(NotificationsConfig $config)
    {
        foreach (['general', 'system', 'account', 'security'] as $id) {
            $this->categories[$id] = new NotificationCategory($id, 'gcego-notifications::messages.categories.'.$id);
        }

        foreach ($config->categories as $id => $category) {
            $this->categories[$id] = new NotificationCategory($id, $category['label'] ?? $id, $category['mandatory'] ?? []);
        }
    }

    public function register(NotificationCategory $category): void
    {
        if (preg_match(NotificationCategory::IdPattern, $category->id) !== 1) {
            throw InvalidNotificationException::invalid('Category "'.$category->id.'"', 'use lowercase letters, digits, "-" and "_".');
        }

        $this->categories[$category->id] = $category;
    }

    public function has(string $id): bool
    {
        return isset($this->categories[$id]);
    }

    public function get(string $id): NotificationCategory
    {
        return $this->categories[$id] ?? throw InvalidNotificationException::invalid('Category "'.mb_substr($id, 0, 48).'"', 'it is not registered (gcego-notifications.categories).');
    }

    /**
     * @return array<string, NotificationCategory>
     */
    public function all(): array
    {
        return $this->categories;
    }
}
