<?php

declare(strict_types=1);

namespace Gcego\Studio\Tests\Fixtures\Policies;

use Gcego\Studio\Tests\Fixtures\Models\Subject;
use Gcego\Studio\Tests\Fixtures\Models\User;

/**
 * Members read subjects; Editors and Admins create and edit them; Readers only read.
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
}
