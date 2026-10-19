<?php

declare(strict_types=1);

namespace Gcego\Layouts\Tests\Fixtures\Policies;

use Gcego\Layouts\Tests\Fixtures\Models\User;

final class Denying
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, mixed $record): bool
    {
        return false;
    }
}
