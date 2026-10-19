<?php

declare(strict_types=1);

namespace Gcego\Security\Middleware;

use Closure;
use Gcego\Security\Resources\DefinitionAuthorizer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects a route with a registered definition's requirements:
 *
 *     Route::get('/admin/subjects', ...)->middleware('gcego.authorize:blueprint:subjects');
 *     Route::post('/admin/subjects/export', ...)->middleware('gcego.authorize:blueprint:subjects,subjects.export');
 *
 * The identity comes from the route definition (trusted), never from the
 * request. Unknown identities and denials raise AccessDeniedException (404/403).
 */
final readonly class AuthorizeDefinition
{
    public const string Alias = 'gcego.authorize';

    public function __construct(private DefinitionAuthorizer $definitions) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $identity, ?string $path = null): Response
    {
        $this->definitions->identity($identity, $path)->authorize();

        return $next($request);
    }
}
