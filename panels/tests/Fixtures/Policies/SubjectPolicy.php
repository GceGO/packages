<?php

declare(strict_types=1);

namespace Gcego\Panels\Tests\Fixtures\Policies;

use Gcego\Panels\Tests\Fixtures\Models\Subject;
use Gcego\Panels\Tests\Fixtures\Models\User;

/**
 * Members read; Readers may not write; only Admins delete; nobody may see "Locked".
 * Visitors may not even list subjects.
 */
final class SubjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->name !== 'Visitor';
    }

    public function view(User $user, Subject $subject): bool
    {
        return $user->name !== 'Visitor' && $subject->getAttribute('name') !== 'Locked';
    }

    public function create(User $user): bool
    {
        return in_array($user->name, ['Admin', 'Editor'], true);
    }

    public function update(User $user, Subject $subject): bool
    {
        return in_array($user->name, ['Admin', 'Editor'], true) && $subject->getAttribute('name') !== 'Locked';
    }

    public function delete(User $user, Subject $subject): bool
    {
        return $user->name === 'Admin';
    }
}
