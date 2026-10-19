<?php

declare(strict_types=1);

namespace Gcego\Pages\Tests\Fixtures\Policies;

use Gcego\Pages\Tests\Fixtures\Models\User;

final class Permissive
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, mixed $record): bool
    {
        return true;
    }
}
