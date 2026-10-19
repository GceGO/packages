<?php

declare(strict_types=1);

namespace Gcego\Widgets\Configuration;

use Gcego\Components\Contracts\ConfigurationNode;
use Gcego\Widgets\Exceptions\InvalidWidgetException;

/**
 * Opt-in caching of a widget's data (also needs gcego-widgets.cache.enabled):
 *
 *     new Cache(ttl: 600)                      // per user (default): safe for any data
 *     new Cache(ttl: 600, scope: 'tenant')     // shared by the tenant's authorized users: only for data that does not depend on the user
 *
 * Keys always include the widget, the child, the state (period, page), the
 * record and the tenant; "user" scope also includes the user and the guard.
 * Authorization is checked on every request before the cache is read, so
 * a cached value is only served to users allowed to see the widget. Only
 * the data result is cached, never users or models. WidgetManager::forget()
 * invalidates every key of a widget.
 */
final readonly class Cache implements ConfigurationNode
{
    public function __construct(public ?int $ttl = null, public string $scope = 'user')
    {
        if ($ttl !== null && ($ttl < 1 || $ttl > 86400)) {
            throw InvalidWidgetException::invalid('Cache', 'ttl must be between 1 and 86400 seconds.');
        }

        if (! in_array($scope, ['user', 'tenant'], true)) {
            throw InvalidWidgetException::invalid('Cache', 'scope must be "user" or "tenant".');
        }
    }

    /**
     * @return array{ttl: int|null, scope: string}
     */
    public function normalize(): array
    {
        return ['ttl' => $this->ttl, 'scope' => $this->scope];
    }
}
