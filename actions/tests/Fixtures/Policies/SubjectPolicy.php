<?php

declare(strict_types=1);

namespace Gcego\Actions\Tests\Fixtures\Policies;

use Gcego\Actions\Tests\Fixtures\Models\Subject;
use Gcego\Actions\Tests\Fixtures\Models\User;

/**
 * Reader: read only. Editor: create, update, publish. Admin: everything.
 */
final class SubjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Subject $subject): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->name, ['Editor', 'Admin'], true);
    }

    public function update(User $user, Subject $subject): bool
    {
        return in_array($user->name, ['Editor', 'Admin'], true) && $subject->code !== 'LOCKED';
    }

    public function delete(User $user, Subject $subject): bool
    {
        return $user->name === 'Admin' && $subject->code !== 'LOCKED';
    }

    public function restore(User $user, Subject $subject): bool
    {
        return $user->name === 'Admin';
    }

    public function forceDelete(User $user, Subject $subject): bool
    {
        return $user->name === 'Admin';
    }

    public function publish(User $user, Subject $subject): bool
    {
        return in_array($user->name, ['Editor', 'Admin'], true);
    }
}
