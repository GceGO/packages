<?php

declare(strict_types=1);

namespace Gcego\Testing\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Creates test users with the application's own model and factory.
 *
 * The toolkit assumes no user schema: implement this when the default
 * (the auth provider's model, through its factory when it has one) does
 * not fit.
 */
interface UserFixtureProvider
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes = []): Authenticatable;
}
