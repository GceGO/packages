<?php

declare(strict_types=1);

namespace Gcego\Settings\Events;

/**
 * An import committed (after its SettingsUpdated event).
 */
final readonly class SettingsImported
{
    /**
     * @param  list<string>  $keys
     */
    public function __construct(
        public array $keys,
        public string $scope,
        public ?string $tenant,
        public string $actor,
        public string $correlation,
    ) {}
}
