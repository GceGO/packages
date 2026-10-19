<?php

declare(strict_types=1);

namespace Gcego\Vault\Authorization;

use Gcego\Vault\Support\Context;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * Lets Laravel's Gate answer Vault permission names:
 *
 *     $user->can('reports.export');  Gate::authorize('subjects.publish');  @can('vault.roles.viewAny')
 *
 * Registered as a Gate *after* callback, which Laravel consults only when
 * nothing else answered: a policy method, Gate::define or before-callback
 * always wins, so a generic permission can never override a policy's
 * record conditions. Only dotted names without arguments are considered;
 * policy abilities ("update", $subject) are never touched. Global scope only.
 */
final readonly class GateIntegration
{
    public function __construct(private Container $container) {}

    public function register(Gate $gate): void
    {
        $container = $this->container;

        $gate->after(static function (?Authenticatable $user, string $ability, mixed $result, array $arguments) use ($container): ?bool {
            if ($result !== null || $arguments !== [] || ! $user instanceof Model || ! str_contains($ability, '.')) {
                return null;
            }

            return $container->make(PermissionEvaluator::class)->allows($user, $ability, '', $container->make(Context::class)->panel());
        });
    }
}
