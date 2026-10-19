<?php

declare(strict_types=1);

namespace Gcego\Widgets\Tests\Fixtures\Policies;

use Gcego\Widgets\Tests\Fixtures\Models\User;

/**
 * Payments: only the Bursar may list them.
 */
final class FinancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->name === 'Bursar';
    }
}
