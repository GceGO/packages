<?php

declare(strict_types=1);

namespace Gcego\Settings\Events;

use Gcego\Settings\History\ChangeType;

/**
 * One setting changed at one scope instance (stored, replaced, imported or
 * applied after approval), dispatched after the change committed.
 *
 * It carries no value, so secrets never reach listeners, queues or logs:
 * read the new value through SettingsManager when needed. Handlers of
 * change effects (refresh navigation, clear a derived cache, reconfigure
 * an integration) should be idempotent; a correlation id ties the events
 * of one write together.
 */
final readonly class SettingUpdated
{
    public function __construct(
        public string $key,
        public string $group,
        public string $scope,
        public ?string $tenant,
        public ChangeType $change,
        public int $version,
        public bool $sensitive,
        public string $actor,
        public string $correlation,
    ) {}
}
