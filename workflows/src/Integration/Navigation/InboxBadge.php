<?php

declare(strict_types=1);

namespace Gcego\Workflows\Integration\Navigation;

use Gcego\Navigation\Badges\NavigationBadge;
use Gcego\Navigation\Contracts\NavigationBadgeProvider;
use Gcego\Navigation\Resolution\NavigationContext;
use Gcego\Workflows\Tasks\Inbox;
use Illuminate\Database\Eloquent\Model;

/**
 * The number of open approvals and tasks of the signed-in user, for a navigation item:
 *
 *     new Item('inbox', 'Workflow inbox', new Property(Item::Badge, InboxBadge::class))
 */
final class InboxBadge implements NavigationBadgeProvider
{
    public function badge(NavigationContext $context): ?NavigationBadge
    {
        return $context->user instanceof Model ? NavigationBadge::count(app(Inbox::class)->count($context->user), (string) __('gcego-workflows::messages.pending')) : null;
    }
}
