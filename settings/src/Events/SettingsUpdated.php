<?php

declare(strict_types=1);

namespace Gcego\Settings\Events;

/**
 * One write committed (set, setMany, reset, import or an applied approval):
 * the keys it changed at one scope instance. Dispatched once, after the
 * per-setting events; carries no values.
 */
final readonly class SettingsUpdated
{
    /**
     * @param  list<string>  $keys
     * @param  list<string>  $groups
     */
    public function __construct(
        public array $keys,
        public array $groups,
        public string $scope,
        public ?string $tenant,
        public string $actor,
        public string $correlation,
        public bool $sensitive,
    ) {}
}
