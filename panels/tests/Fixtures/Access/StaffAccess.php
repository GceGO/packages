<?php

declare(strict_types=1);

namespace Gcego\Panels\Tests\Fixtures\Access;

use Gcego\Panels\Context\PanelContext;
use Gcego\Panels\Contracts\PanelAccessPolicy;
use Gcego\Panels\Tests\Fixtures\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

final class StaffAccess implements PanelAccessPolicy
{
    public function allows(Authenticatable $user, PanelContext $context): bool
    {
        return $user instanceof User && in_array($user->name, ['Admin', 'Editor'], true);
    }
}
