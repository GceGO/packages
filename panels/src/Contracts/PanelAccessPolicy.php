<?php

declare(strict_types=1);

namespace Gcego\Panels\Contracts;

use Gcego\Panels\Context\PanelContext;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Decides whether a signed-in user may enter a panel (Panel::AccessPolicy):
 *
 *     final class SuperAdminPanelAccess implements PanelAccessPolicy
 *     {
 *         public function allows(Authenticatable $user, PanelContext $context): bool
 *         {
 *             return Gate::forUser($user)->allows('access-super-admin');
 *         }
 *     }
 *
 * It is checked on every panel request and every Livewire update, with
 * the panel's requirements (both must allow). $context carries the
 * verified tenant, if any. Resolved from the container (constructor
 * injection works); it must not depend on request input.
 */
interface PanelAccessPolicy
{
    public function allows(Authenticatable $user, PanelContext $context): bool;
}
