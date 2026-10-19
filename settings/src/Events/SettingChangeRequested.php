<?php

declare(strict_types=1);

namespace Gcego\Settings\Events;

/**
 * A change waits for its approval workflow (Setting::Approval); nothing
 * was applied yet. request is the gcego_setting_requests ULID.
 */
final readonly class SettingChangeRequested
{
    public function __construct(
        public string $key,
        public string $group,
        public string $scope,
        public ?string $tenant,
        public string $workflow,
        public string $request,
        public string $actor,
        public string $correlation,
    ) {}
}
