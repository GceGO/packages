<?php

declare(strict_types=1);

namespace Gcego\Vault\Middleware;

use Closure;
use Gcego\Security\SecurityManager;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware for routes outside panels and modules:
 *
 *     Route::get('/reports', ...)->middleware(['auth', 'vault.permission:reports.view']);
 *     Route::get('/grades', ...)->middleware(['auth', 'gcego.tenant:required', 'vault.permission:grades.view,tenant']);
 *
 * Decided by gcego/security (and so by Vault): unknown permissions and
 * guests are denied with gcego/security's status.
 */
final readonly class RequirePermission
{
    public const string Alias = 'vault.permission';

    public function __construct(private SecurityManager $security) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $permission, string $scope = 'global'): Response
    {
        $this->security->permission($permission, tenant: $scope === 'tenant')->authorize();

        return $next($request);
    }
}
