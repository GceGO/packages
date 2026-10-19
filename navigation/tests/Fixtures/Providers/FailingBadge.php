<?php

declare(strict_types=1);

namespace Gcego\Navigation\Tests\Fixtures\Providers;

use Gcego\Navigation\Badges\NavigationBadge;
use Gcego\Navigation\Contracts\NavigationBadgeProvider;
use Gcego\Navigation\Resolution\NavigationContext;
use RuntimeException;

final class FailingBadge implements NavigationBadgeProvider
{
    public function badge(NavigationContext $context): NavigationBadge|int|null
    {
        throw new RuntimeException('SQLSTATE[42S02]: table secret_requests missing');
    }
}
