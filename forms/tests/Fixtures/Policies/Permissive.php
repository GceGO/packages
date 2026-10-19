<?php

declare(strict_types=1);

namespace Gcego\Forms\Tests\Fixtures\Policies;

use Gcego\Forms\Tests\Fixtures\Models\User;

/**
 * Reader: read only. Editor and Admin: create and update.
 */
final class Permissive
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, mixed $record): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->name !== 'Reader';
    }

    public function update(User $user, mixed $record): bool
    {
        return $user->name !== 'Reader';
    }
}
