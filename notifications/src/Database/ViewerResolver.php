<?php

declare(strict_types=1);

namespace Gcego\Notifications\Database;

use Gcego\Notifications\Exceptions\NotificationAuthorizationException;
use Gcego\Panels\Context\PanelContextResolver;
use Gcego\Security\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Database\Eloquent\Model;

/**
 * The current viewer: the user of the current panel's guard (or the
 * default guard), the verified tenant (gcego/security) and the panel
 * (gcego/panels). Outside a tenant, only tenant-less notifications show.
 */
final readonly class ViewerResolver
{
    public function __construct(private Auth $auth, private TenantContext $tenants, private PanelContextResolver $panels) {}

    public function current(): ?NotificationViewer
    {
        $panel = $this->panels->current();
        $user = $panel !== null ? $panel->user : $this->auth->guard()->user();

        if (! $user instanceof Model) {
            return null;
        }

        $tenant = $this->tenants->tenant();

        return new NotificationViewer($user, $tenant === null ? null : (string) $tenant->key, $panel?->name());
    }

    /**
     * @throws NotificationAuthorizationException
     */
    public function require(): NotificationViewer
    {
        return $this->current() ?? throw NotificationAuthorizationException::noViewer();
    }
}
