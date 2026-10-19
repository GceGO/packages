<?php

declare(strict_types=1);

namespace Gcego\Media\Tests\Fixtures\Policies;

use Gcego\Media\Tests\Fixtures\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Topics and tags: members read; Admins and Editors write; Admins delete; "Hidden" records are invisible.
 */
final class RecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->name !== 'Visitor';
    }

    public function view(User $user, Model $record): bool
    {
        return $user->name !== 'Visitor' && $record->getAttribute('name') !== 'Hidden';
    }

    public function create(User $user): bool
    {
        return in_array($user->name, ['Admin', 'Editor'], true);
    }

    public function update(User $user, Model $record): bool
    {
        return in_array($user->name, ['Admin', 'Editor'], true);
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->name === 'Admin';
    }
}
