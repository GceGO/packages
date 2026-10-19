<?php

declare(strict_types=1);

namespace Gcego\Navigation\Tests\Fixtures\Policies;

use Gcego\Navigation\Tests\Fixtures\Models\User;

/**
 * Reader: read only. Editor and Admin: everything.
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

    public function delete(User $user, mixed $record): bool
    {
        return $user->name !== 'Reader';
    }

    public function restore(User $user, mixed $record): bool
    {
        return $user->name !== 'Reader';
    }

    public function archive(User $user, mixed $record): bool
    {
        return $user->name === 'Admin';
    }
}
