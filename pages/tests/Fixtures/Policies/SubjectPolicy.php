<?php

declare(strict_types=1);

namespace Gcego\Pages\Tests\Fixtures\Policies;

use Gcego\Pages\Tests\Fixtures\Models\Subject;
use Gcego\Pages\Tests\Fixtures\Models\User;

/**
 * Everyone may read; Readers may not write; only Admins delete; nobody may see "Locked" subjects.
 */
final class SubjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Subject $subject): bool
    {
        return $subject->getAttribute('name') !== 'Locked';
    }

    public function create(User $user): bool
    {
        return $user->name !== 'Reader';
    }

    public function update(User $user, Subject $subject): bool
    {
        return $user->name !== 'Reader' && $subject->getAttribute('name') !== 'Locked';
    }

    public function delete(User $user, Subject $subject): bool
    {
        return $user->name === 'Admin';
    }
}
