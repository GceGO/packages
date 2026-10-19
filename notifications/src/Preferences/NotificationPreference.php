<?php

declare(strict_types=1);

namespace Gcego\Notifications\Preferences;

/**
 * One stored choice: a channel on or off, for a category ("*": every
 * category) in a tenant ("": every tenant).
 */
final readonly class NotificationPreference
{
    public const string AnyCategory = '*';

    public const string AnyTenant = '';

    public function __construct(public string $category, public string $channel, public bool $enabled, public string $tenant = self::AnyTenant) {}

    /**
     * Higher is more specific: tenant + category, tenant, category, neither.
     */
    public function specificity(): int
    {
        return ($this->tenant !== self::AnyTenant ? 2 : 0) + ($this->category !== self::AnyCategory ? 1 : 0);
    }
}
