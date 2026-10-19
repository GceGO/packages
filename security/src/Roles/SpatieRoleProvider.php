<?php

declare(strict_types=1);

namespace Gcego\Security\Roles;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Contracts\RoleProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Throwable;

/**
 * Global roles from spatie/laravel-permission (getRoleNames()).
 */
final class SpatieRoleProvider implements RoleProvider
{
    public function roles(Authenticatable $user, AuthorizationContext $context): array
    {
        if (! method_exists($user, 'getRoleNames')) {
            return [];
        }

        try {
            $names = $user->getRoleNames();
        } catch (Throwable) {
            return [];
        }

        $roles = [];

        foreach (is_iterable($names) ? $names : [] as $name) {
            if (is_string($name)) {
                $roles[] = $name;
            }
        }

        return $roles;
    }
}
