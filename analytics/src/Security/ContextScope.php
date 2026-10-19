<?php

declare(strict_types=1);

namespace Gcego\Analytics\Security;

use Closure;
use Gcego\Security\SecurityManager;
use Gcego\Security\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Runs analytics work as a context's user in its tenant. When the request
 * already runs as exactly that user in that (verified) tenant, it runs
 * directly; otherwise gcego/security's actingAs() switches, verifying the
 * tenant membership (a forged tenant fails there).
 */
final readonly class ContextScope
{
    public function __construct(private SecurityManager $security, private TenantContext $tenants, private AuthFactory $auth) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(AnalyticsContext $context, Closure $callback): mixed
    {
        $tenant = $this->tenants->tenant();
        $current = $this->auth->guard()->user();
        $sameUser = $current === $context->user || ($current !== null && $context->user !== null && $current::class === $context->user::class && $current->getAuthIdentifier() === $context->user->getAuthIdentifier());
        $sameTenant = ($tenant === null ? null : (string) $tenant->key) === $context->tenant;

        return $sameUser && $sameTenant ? $callback() : $this->security->actingAs($context->user, $context->tenant, $callback);
    }
}
