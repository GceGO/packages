<?php

declare(strict_types=1);

namespace Gcego\Panels\Context;

use Gcego\Panels\Registry\PanelDescriptor;
use Gcego\Security\Tenancy\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The panel of the current request, after authentication, tenancy and
 * panel access were verified. Request-scoped server state: it is never
 * serialized into Livewire components or sent to the browser.
 */
final readonly class PanelContext
{
    public function __construct(
        public PanelDescriptor $panel,
        public Authenticatable $user,
        public string $guard,
        public ?Tenant $tenant,
        public string $routeNamespace,
    ) {}

    public function name(): string
    {
        return $this->panel->name;
    }

    public function identity(): string
    {
        return $this->panel->identity;
    }

    public function tenantKey(): ?string
    {
        return $this->tenant === null ? null : (string) $this->tenant->key;
    }

    /**
     * Whether a route name belongs to this panel.
     */
    public function owns(?string $routeName): bool
    {
        return $routeName !== null && str_starts_with($routeName, $this->routeNamespace);
    }
}
