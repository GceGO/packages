<?php

declare(strict_types=1);

namespace Gcego\Testing\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The acting user and tenant of the current simulated request.
 */
interface TestEnvironment
{
    public function actingAs(?Authenticatable $user, string|int|null $tenant = null, ?string $guard = null): void;

    public function guest(): void;

    public function enterTenant(string $tenant): void;

    public function leaveTenant(): void;

    public function currentTenant(): ?string;

    public function user(): ?Authenticatable;

    public function newRequest(): void;

    /**
     * Makes $uri the current request (route matched and bound), keeping the
     * signed-in user and tenant; $forget lists services to rebuild.
     *
     * @param  list<class-string>  $forget
     */
    public function onPage(string $uri, array $forget = []): void;
}
