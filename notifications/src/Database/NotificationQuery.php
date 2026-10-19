<?php

declare(strict_types=1);

namespace Gcego\Notifications\Database;

use DateTimeImmutable;
use Gcego\Notifications\Definitions\NotificationCategory;

/**
 * Inbox filters (validated; invalid values are ignored, not trusted).
 */
final readonly class NotificationQuery
{
    public const array Statuses = ['all', 'unread', 'read'];

    public function __construct(
        public string $status = 'all',
        public ?string $category = null,
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $until = null,
    ) {}

    /**
     * From untrusted input (Livewire properties, query strings).
     */
    public static function fromInput(mixed $status, mixed $category = null, mixed $from = null, mixed $until = null): self
    {
        $date = static function (mixed $value, bool $end): ?DateTimeImmutable {
            if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
                return null;
            }

            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

            return $date === false ? null : ($end ? $date->setTime(23, 59, 59) : $date);
        };

        return new self(
            status: is_string($status) && in_array($status, self::Statuses, true) ? $status : 'all',
            category: is_string($category) && preg_match(NotificationCategory::IdPattern, $category) === 1 ? $category : null,
            from: $date($from, false),
            until: $date($until, true),
        );
    }
}
