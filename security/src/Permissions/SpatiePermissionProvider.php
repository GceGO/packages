<?php

declare(strict_types=1);

namespace Gcego\Security\Permissions;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Contracts\PermissionProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Throwable;

/**
 * Permissions from spatie/laravel-permission, read directly with
 * hasPermissionTo($permission, $guard): direct and role permissions count;
 * Gate::before rules do not. A permission Spatie does not know is reported
 * as unknown (denied). The user model must use Spatie's HasRoles trait.
 */
final class SpatiePermissionProvider implements PermissionProvider
{
    public function allows(Authenticatable $user, string $permission, AuthorizationContext $context): ?bool
    {
        if (! method_exists($user, 'hasPermissionTo')) {
            return null;
        }

        try {
            return (bool) $user->hasPermissionTo($permission, $context->guard);
        } catch (Throwable) {
            // PermissionDoesNotExist or GuardDoesNotMatch: unknown here, never an allow.
            return null;
        }
    }
}
