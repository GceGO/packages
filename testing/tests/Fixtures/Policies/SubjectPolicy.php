<?php

declare(strict_types=1);

namespace Gcego\Testing\Tests\Fixtures\Policies;

use Gcego\Testing\Tests\Fixtures\Models\Subject;
use Gcego\Testing\Tests\Fixtures\Models\User;

/**
 * Everyone signed in may list and view; Admin and Editor may create and
 * update; only Admin may delete; nobody may update a published subject's
 * code (the "locked" case).
 */
final class SubjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Subject $subject): bool
    {
        return $subject->published || in_array($user->name, ['Admin', 'Editor'], true);
    }

    public function create(User $user): bool
    {
        return in_array($user->name, ['Admin', 'Editor'], true);
    }

    public function update(User $user, Subject $subject): bool
    {
        return in_array($user->name, ['Admin', 'Editor'], true);
    }

    public function delete(User $user, Subject $subject): bool
    {
        return $user->name === 'Admin';
    }

    public function publish(User $user, Subject $subject): bool
    {
        return $user->name === 'Admin';
    }
}
