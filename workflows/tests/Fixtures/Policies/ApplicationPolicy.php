<?php

declare(strict_types=1);

namespace Gcego\Workflows\Tests\Fixtures\Policies;

use Gcego\Workflows\Tests\Fixtures\Models\Application;
use Gcego\Workflows\Tests\Fixtures\Models\User;

/**
 * Members see applications (not Visitors); Editors and Admins update them
 * (never "Locked"); reviewers and Admins review.
 */
final class ApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->name !== 'Visitor';
    }

    public function view(User $user, Application $application): bool
    {
        return $user->name !== 'Visitor';
    }

    public function update(User $user, Application $application): bool
    {
        return in_array($user->name, ['Admin', 'Editor'], true) && $application->name !== 'Locked';
    }

    public function review(User $user, Application $application): bool
    {
        return $user->name === 'Admin' || str_starts_with($user->name, 'Reviewer');
    }
}
