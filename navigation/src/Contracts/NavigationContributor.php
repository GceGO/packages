<?php

declare(strict_types=1);

namespace Gcego\Navigation\Contracts;

use Gcego\Navigation\NavigationNode;

/**
 * Adds nodes to a registered navigation from trusted code (modules, pages,
 * panels, packages). Register the class (NavigationManager::contribute() or
 * gcego-navigation.contributors); it is resolved lazily, the first time the
 * navigation is built in a process, and its nodes are static definitions.
 *
 * Contributed groups whose Name matches a group of the navigation are merged
 * into it; everything else is added at the root. Nodes are ordered by Order,
 * then by contributor registration order, then declaration order.
 */
interface NavigationContributor
{
    /**
     * The navigation name or identity ("admin" or "navigation:admin").
     */
    public function navigation(): string;

    /**
     * @return iterable<NavigationNode>
     */
    public function nodes(): iterable;
}
