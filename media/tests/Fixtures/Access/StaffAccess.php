<?php

declare(strict_types=1);

namespace Gcego\Media\Tests\Fixtures\Access;

use Gcego\Media\Tests\Fixtures\Models\User;
use Gcego\Panels\Context\PanelContext;
use Gcego\Panels\Contracts\PanelAccessPolicy;
use Illuminate\Contracts\Auth\Authenticatable;

final class StaffAccess implements PanelAccessPolicy
{
    public function allows(Authenticatable $user, PanelContext $context): bool
    {
        return $user instanceof User && in_array($user->name, ['Admin', 'Editor'], true);
    }
}
