<?php

declare(strict_types=1);

namespace Gcego\Navigation\Contracts;

use Gcego\Navigation\Badges\NavigationBadge;
use Gcego\Navigation\Resolution\NavigationContext;

/**
 * Computes a badge when a visible item renders (never at definition or boot).
 * Read data as the current user through $context->count() or gcego/data, so
 * policies and tenant scopes apply; keep it to one bounded query.
 */
interface NavigationBadgeProvider
{
    /**
     * A badge, a count (0 shows nothing), or null.
     */
    public function badge(NavigationContext $context): NavigationBadge|int|null;
}
