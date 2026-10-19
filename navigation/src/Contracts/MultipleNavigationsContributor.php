<?php

declare(strict_types=1);

namespace Gcego\Navigation\Contracts;

use Gcego\Navigation\NavigationNode;

/**
 * A contributor that adds nodes to several navigations (gcego/panels: one
 * navigation per panel). navigation() is still the primary target;
 * contributesTo() decides for every other navigation, and nodesFor()
 * returns that navigation's nodes. Trees are cached per navigation, so the
 * nodes must not depend on the current request (requirements are evaluated
 * later, per user).
 */
interface MultipleNavigationsContributor extends NavigationContributor
{
    /**
     * Whether this contributor adds to the navigation ("navigation:panel-admin").
     */
    public function contributesTo(string $identity): bool;

    /**
     * @return iterable<NavigationNode>
     */
    public function nodesFor(string $identity): iterable;
}
