<?php

declare(strict_types=1);

namespace Gcego\Analytics\Tests\Fixtures\Policies;

use Gcego\Analytics\Tests\Fixtures\Models\User;

/**
 * Members may list records, except Visitors.
 */
final class RecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->name !== 'Visitor';
    }

    public function view(User $user, mixed $record): bool
    {
        return $user->name !== 'Visitor';
    }
}
