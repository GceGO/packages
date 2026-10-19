<?php

declare(strict_types=1);

namespace Gcego\Navigation\Tests\Fixtures\Providers;

use Gcego\Navigation\Contracts\NavigationBadgeProvider;
use Gcego\Navigation\Resolution\NavigationContext;
use Gcego\Navigation\Tests\Fixtures\Models\AccessRequest;

final class PendingRequestsBadge implements NavigationBadgeProvider
{
    public static int $calls = 0;

    public function badge(NavigationContext $context): int
    {
        self::$calls++;

        return $context->count(AccessRequest::class);
    }
}
