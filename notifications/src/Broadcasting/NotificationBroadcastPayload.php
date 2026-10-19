<?php

declare(strict_types=1);

namespace Gcego\Notifications\Broadcasting;

use Gcego\Notifications\Definitions\NotificationPayload;
use Gcego\Notifications\Delivery\DeliveryContext;

/**
 * What a broadcast carries: the notification id, approved text, its type
 * ("notification_type": Laravel sets "type" to the broadcast type),
 * priority, category, links, tenant and panel (so the browser can ignore
 * other workspaces of the same user) and a timestamp. No metadata, no
 * recipient, no model.
 */
final class NotificationBroadcastPayload
{
    /**
     * @return array{id: string, title: string, body: string|null, notification_type: string, priority: string, category: string, icon: string, actions: list<array{label: string, url: string, primary: bool, new_tab: bool}>, tenant: string|null, panel: string|null, at: string}
     */
    public static function make(string $id, NotificationPayload $payload, DeliveryContext $context): array
    {
        return [
            'id' => $id,
            'title' => mb_substr($payload->displayTitle(), 0, 200),
            'body' => $payload->displayBody() === null ? null : mb_substr((string) $payload->displayBody(), 0, 300),
            'notification_type' => $payload->type->value,
            'priority' => $payload->priority->value,
            'category' => $payload->category,
            'icon' => $payload->icon,
            'actions' => $payload->actions,
            'tenant' => $context->tenant,
            'panel' => $context->panel,
            'at' => now()->toIso8601String(),
        ];
    }
}
