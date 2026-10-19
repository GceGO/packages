<?php

declare(strict_types=1);

namespace Gcego\Security\Tests\Fixtures\Policies;

use Gcego\Security\Tests\Fixtures\Models\Subject;
use Gcego\Security\Tests\Fixtures\Models\User;
use Illuminate\Auth\Access\Response;

final class SubjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Subject $subject): bool
    {
        return $subject->is_published || $user->name === 'Editor';
    }

    public function update(User $user, Subject $subject): bool
    {
        return $user->name === 'Editor';
    }

    public function delete(User $user, Subject $subject): Response
    {
        return Response::deny('Only curriculum owners may delete subjects.');
    }

    public function restore(User $user, Subject $subject): Response
    {
        return Response::denyAsNotFound();
    }

    public function export(?User $user, Subject $subject): bool
    {
        return $user !== null && $user->can('subjects.export');
    }
}
