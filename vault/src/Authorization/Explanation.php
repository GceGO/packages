<?php

declare(strict_types=1);

namespace Gcego\Vault\Authorization;

/**
 * Why Vault answered a permission question the way it did: for the
 * diagnostics screen, gcego:vault:check and tests. Only people allowed to
 * run diagnostics see it; end users get the generic denial message.
 */
final readonly class Explanation
{
    public const string Granted = 'granted';

    public const string SuperAdministrator = 'super_administrator';

    public const string NotGranted = 'not_granted';

    public const string UnknownPermission = 'unknown_permission';

    public const string WrongScope = 'wrong_scope';

    public const string SuperExcluded = 'super_excluded';

    /**
     * @param  list<string>  $sources
     */
    public function __construct(
        public string $permission,
        public bool $allowed,
        public string $reason,
        public string $tenant,
        public ?string $panel,
        public array $sources = [],
    ) {}

    public function message(): string
    {
        return (string) __('gcego-vault::messages.explanations.'.$this->reason, [
            'permission' => $this->permission,
            'scope' => $this->tenant === '' ? __('gcego-vault::messages.scope.global') : $this->tenant,
            'sources' => implode(', ', $this->sources),
        ]);
    }

    /**
     * @return array{permission: string, allowed: bool, reason: string, tenant: string, panel: string|null, sources: list<string>, message: string}
     */
    public function toArray(): array
    {
        return ['permission' => $this->permission, 'allowed' => $this->allowed, 'reason' => $this->reason, 'tenant' => $this->tenant, 'panel' => $this->panel, 'sources' => $this->sources, 'message' => $this->message()];
    }
}
