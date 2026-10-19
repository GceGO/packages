<?php

declare(strict_types=1);

namespace Gcego\Forms\Tests\Fixtures\Policies;

use Gcego\Forms\Tests\Fixtures\Models\User;

final class NoAccess
{
    public function viewAny(User $user): bool
    {
        return false;
    }
}
