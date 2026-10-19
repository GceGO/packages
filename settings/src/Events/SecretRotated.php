<?php

declare(strict_types=1);

namespace Gcego\Settings\Events;

/**
 * A sensitive setting received a new value (rotate(), or a replaced secret
 * in a form). Neither the old nor the new value is part of the event.
 */
final readonly class SecretRotated
{
    public function __construct(
        public string $key,
        public string $group,
        public string $scope,
        public ?string $tenant,
        public int $version,
        public string $actor,
        public string $correlation,
    ) {}
}
