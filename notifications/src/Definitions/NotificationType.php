<?php

declare(strict_types=1);

namespace Gcego\Notifications\Definitions;

/**
 * How a notification looks (presentation only: never priority or authorization).
 */
enum NotificationType: string
{
    case Success = 'success';
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';
    case Neutral = 'neutral';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }

    public function icon(): string
    {
        return match ($this) {
            self::Success => 'status.success',
            self::Error => 'status.error',
            self::Warning => 'status.warning',
            self::Info, self::Neutral => 'status.info',
        };
    }

    /**
     * The screen-reader label announced before the text.
     */
    public function label(): string
    {
        return (string) __('gcego-notifications::messages.types.'.$this->value);
    }
}
