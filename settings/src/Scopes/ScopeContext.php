<?php

declare(strict_types=1);

namespace Gcego\Settings\Scopes;

use Gcego\Security\Tenancy\Tenant;

/**
 * Where a read or write happens, built by ScopeResolver from server state
 * only (the verified tenant of the request, the verified panel, the
 * signed-in user, or values trusted code passed in), never from browser
 * input.
 *
 * - target: the level addressed. Writes store at it; reads see it and
 *   every less specific level that is known (user > tenant > panel > global),
 *   in the setting's precedence order.
 * - panel, tenant and user: the levels known in this context.
 *
 * Plain data, request-local, safe to log (toArray() names levels only).
 */
final readonly class ScopeContext
{
    private const array Specificity = ['global' => 0, 'panel' => 1, 'tenant' => 2, 'user' => 3];

    public function __construct(
        public SettingScope $target,
        public ?string $panel = null,
        public ?Tenant $tenant = null,
        public ?string $user = null,
    ) {}

    /**
     * The scope key of a level in this context ("" for global), or null when the level is unknown here.
     */
    public function keyOf(SettingScope $scope): ?string
    {
        return match ($scope) {
            SettingScope::Global => '',
            SettingScope::Panel => $this->panel,
            SettingScope::Tenant => $this->tenant?->id(),
            SettingScope::User => $this->user,
        };
    }

    /**
     * The scope key writes go to (null when the target level is unknown here).
     */
    public function targetKey(): ?string
    {
        return $this->keyOf($this->target);
    }

    /**
     * Whether reads at the target include this level.
     */
    public function includes(SettingScope $scope): bool
    {
        return self::Specificity[$scope->value] <= self::Specificity[$this->target->value] && $this->keyOf($scope) !== null;
    }

    public function at(SettingScope $target): self
    {
        return new self($target, $this->panel, $this->tenant, $this->user);
    }

    public function withTenant(?Tenant $tenant): self
    {
        return new self($this->target, $this->panel, $tenant, $this->user);
    }

    /**
     * A stable identity of the target ("tenant:institution:12"), for locked Livewire state and history.
     */
    public function identity(): string
    {
        return $this->target->value.':'.($this->targetKey() ?? '?');
    }

    /**
     * @return array{target: string, panel: string|null, tenant: string|null, user: bool}
     */
    public function toArray(): array
    {
        return ['target' => $this->target->value, 'panel' => $this->panel, 'tenant' => $this->tenant?->id(), 'user' => $this->user !== null];
    }
}
