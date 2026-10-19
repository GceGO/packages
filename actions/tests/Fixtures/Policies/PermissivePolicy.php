<?php

declare(strict_types=1);

namespace Gcego\Actions\Tests\Fixtures\Policies;

use Gcego\Actions\Tests\Fixtures\Models\User;

final class PermissivePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, mixed $record): bool
    {
        return true;
    }

    public function update(User $user, mixed $record): bool
    {
        return $user->name !== 'Reader';
    }

    public function delete(User $user, mixed $record): bool
    {
        return $user->name !== 'Reader';
    }
}
