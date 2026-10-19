<?php

declare(strict_types=1);

namespace Gcego\Notifications\Definitions;

/**
 * How urgent a notification is. Priority orders and presents notifications
 * (critical errors are announced assertively and stay until dismissed);
 * it never widens who receives a notification or overrides preferences.
 */
enum NotificationPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Critical = 'critical';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $priority): string => $priority->value, self::cases());
    }

    public function rank(): int
    {
        return match ($this) {
            self::Low => 0,
            self::Normal => 1,
            self::High => 2,
            self::Critical => 3,
        };
    }
}
