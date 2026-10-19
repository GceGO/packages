<?php

declare(strict_types=1);

namespace Gcego\Settings\Events;

/**
 * An override was removed: the setting inherits again at that scope
 * instance. Dispatched after the reset committed; carries no value.
 */
final readonly class SettingReset
{
    public function __construct(
        public string $key,
        public string $group,
        public string $scope,
        public ?string $tenant,
        public bool $sensitive,
        public string $actor,
        public string $correlation,
    ) {}
}
