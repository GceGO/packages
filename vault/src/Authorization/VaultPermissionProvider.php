<?php

declare(strict_types=1);

namespace Gcego\Vault\Authorization;

use Gcego\Security\Authorization\AuthorizationContext;
use Gcego\Security\Contracts\PermissionProvider;
use Gcego\Vault\Support\Context;
use Gcego\Vault\VaultConfig;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * gcego/security's global permissions (Permission requirements on panels,
 * modules, pages, actions, fields and columns), answered by Vault.
 *
 * - A permission registered in Vault is answered by Vault alone (global
 *   assignments and grants, super-administrator rule), for subjects Vault
 *   manages; any other user is denied it.
 * - A permission Vault does not know goes to the provider configured before
 *   Vault (gate or spatie), so existing Gate abilities keep working; with no
 *   previous provider it is unknown, which gcego/security denies.
 *
 * Tenant permissions never come from here: gcego/security reads them from
 * the membership, which VaultTenantResolver enriches.
 */
final readonly class VaultPermissionProvider implements PermissionProvider
{
    public function __construct(private Container $container, private ?PermissionProvider $previous = null) {}

    public function allows(Authenticatable $user, string $permission, AuthorizationContext $context): ?bool
    {
        $evaluator = $this->container->make(PermissionEvaluator::class);

        if ($evaluator->graph()->knows($permission)) {
            return $this->isSubject($user)
                ? $evaluator->allows($user, $permission, '', $this->container->make(Context::class)->panel()) === true
                : false;
        }

        return $this->previous?->allows($user, $permission, $context);
    }

    /**
     * @phpstan-assert-if-true Model $user
     */
    private function isSubject(Authenticatable $user): bool
    {
        if (! $user instanceof Model) {
            return false;
        }

        $subjects = $this->container->make(VaultConfig::class)->subjects;

        if ($subjects === []) {
            return true;
        }

        foreach ($subjects as $subject) {
            if ($user instanceof $subject) {
                return true;
            }
        }

        return false;
    }
}
