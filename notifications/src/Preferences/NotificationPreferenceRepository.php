<?php

declare(strict_types=1);

namespace Gcego\Notifications\Preferences;

use Gcego\Notifications\Delivery\Recipients;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

/**
 * gcego_notification_preferences: one row per recipient, tenant, category
 * and channel. Always queried and written for one recipient.
 */
final readonly class NotificationPreferenceRepository
{
    public const string Table = 'gcego_notification_preferences';

    public function __construct(private ConnectionResolverInterface $connections) {}

    /**
     * @return list<NotificationPreference>
     */
    public function for(Model $recipient): array
    {
        $preferences = [];

        foreach ($this->table()->where('notifiable_type', $recipient->getMorphClass())->where('notifiable_id', self::key($recipient))->get(['tenant', 'category', 'channel', 'enabled']) as $row) {
            $row = (array) $row;
            $text = static fn (mixed $value): string => is_scalar($value) ? (string) $value : '';
            $preferences[] = new NotificationPreference($text($row['category'] ?? null), $text($row['channel'] ?? null), in_array($row['enabled'] ?? null, [true, 1, '1'], true), $text($row['tenant'] ?? null));
        }

        return $preferences;
    }

    public function save(Model $recipient, NotificationPreference $preference): void
    {
        $now = now();

        $this->table()->upsert(
            [[
                'notifiable_type' => $recipient->getMorphClass(),
                'notifiable_id' => self::key($recipient),
                'tenant' => $preference->tenant,
                'category' => $preference->category,
                'channel' => $preference->channel,
                'enabled' => $preference->enabled,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['notifiable_type', 'notifiable_id', 'tenant', 'category', 'channel'],
            ['enabled', 'updated_at'],
        );
    }

    public function forget(Model $recipient, string $category, string $channel, string $tenant = NotificationPreference::AnyTenant): void
    {
        $this->table()->where('notifiable_type', $recipient->getMorphClass())->where('notifiable_id', self::key($recipient))
            ->where('tenant', $tenant)->where('category', $category)->where('channel', $channel)->delete();
    }

    private function table(): Builder
    {
        return $this->connections->connection()->table(self::Table);
    }

    private static function key(Model $recipient): string
    {
        return substr(Recipients::reference($recipient), strlen($recipient->getMorphClass()) + 1);
    }
}
