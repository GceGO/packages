<?php

declare(strict_types=1);

namespace Gcego\Cli\Tests\Fixtures\Policies;

use Gcego\Cli\Tests\Fixtures\Models\Subject;
use Gcego\Cli\Tests\Fixtures\Models\User;

/**
 * Everyone signed in reads; Admin and Editor write; Admin publishes.
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

    public function publishSubject(User $user, Subject $subject): bool
    {
        return $user->name === 'Admin';
    }
}
