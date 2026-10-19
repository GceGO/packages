<?php

declare(strict_types=1);

use Gcego\Navigation\NavigationManager;
use Gcego\Navigation\Resolution\NavigationResolver;
use Gcego\Navigation\Resolution\RouteResolver;
use Gcego\Navigation\Resolution\VisibilityResolver;
use Gcego\Navigation\Tests\Fixtures\Models\AccessRequest;
use Gcego\Navigation\Tests\Fixtures\Models\Membership;
use Gcego\Navigation\Tests\Fixtures\Models\Subject;
use Gcego\Navigation\Tests\Fixtures\Models\User;
use Gcego\Navigation\Tests\TestCase;
use Gcego\Security\SecurityManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

pest()->extend(TestCase::class)->in('Unit', 'Integration', 'Feature');

/**
 * Institution 1: subjects Chemistry (1) and Physics (2), three access requests.
 * Institution 2: subject Secret Biology (3), one access request.
 *
 * @return array{teacher: User, admin: User, south: User}
 */
function world(): array
{
    $users = [];

    foreach (['teacher' => 'Teacher', 'admin' => 'Admin'] as $key => $name) {
        $users[$key] = User::query()->create(['name' => $name]);
        Membership::query()->create(['user_id' => $users[$key]->id, 'institution_id' => 1]);
    }

    $users['south'] = User::query()->create(['name' => 'South']);
    Membership::query()->create(['user_id' => $users['south']->id, 'institution_id' => 2]);

    foreach ([['Chemistry', 1], ['Physics', 1], ['Secret Biology', 2]] as [$name, $institution]) {
        Subject::query()->create(['name' => $name, 'institution_id' => $institution]);
    }

    foreach ([1, 1, 1, 2] as $institution) {
        AccessRequest::query()->create(['institution_id' => $institution]);
    }

    return $users;
}

function actAs(?User $user, ?int $institution = 1): void
{
    if ($user !== null) {
        test()->actingAs($user);
    }

    app()->forgetScopedInstances();

    if ($institution !== null) {
        app(SecurityManager::class)->enterTenant((string) $institution);
    }
}

/**
 * Make $uri the current request (route matched), for direct resolution.
 */
function onPage(string $uri): void
{
    $request = Request::create($uri);

    try {
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(static fn () => $route);
        $route->bind($request);
    } catch (NotFoundHttpException) {
        // A page without a named route: path matching only.
    }
    app()->instance('request', $request);

    // Only the navigation resolvers: the security context (user, tenant) stays.
    foreach ([NavigationResolver::class, RouteResolver::class, VisibilityResolver::class] as $resolver) {
        app()->forgetInstance($resolver);
    }
}

function navigation(): NavigationManager
{
    return app(NavigationManager::class);
}

function squish(string $html): string
{
    return trim((string) preg_replace('/\s+/', ' ', $html));
}

/**
 * @return list<string>
 */
function queriesDuring(Closure $callback): array
{
    $queries = [];
    $recording = true;
    Event::listen(QueryExecuted::class, static function (QueryExecuted $query) use (&$queries, &$recording): void {
        if ($recording) {
            $queries[] = $query->sql;
        }
    });

    try {
        $callback();
    } finally {
        $recording = false;
    }

    return $queries;
}

function statusOf(Closure $callback): ?int
{
    try {
        $callback();
    } catch (HttpExceptionInterface $exception) {
        return $exception->getStatusCode();
    } catch (AuthorizationException $exception) {
        return $exception->status() ?? 403;
    }

    return null;
}
