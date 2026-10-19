<?php

declare(strict_types=1);

namespace Gcego\Workflows\Tests\Fixtures\Handlers;

use Gcego\Workflows\Tests\Fixtures\Models\User;

/**
 * Looks people up by name for the fixture resolvers.
 */
final class People
{
    /**
     * @param  list<string>  $names
     * @return list<User>
     */
    public static function named(array $names): array
    {
        return array_values(User::query()->whereIn('name', $names)->orderBy('id')->get()->all());
    }
}
