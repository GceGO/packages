<?php

declare(strict_types=1);

namespace Gcego\Components\Traversal;

/**
 * What a ComponentWalker::walk() visitor wants to happen next.
 */
enum Visit
{
    case Continue;

    /**
     * Do not descend into the current node's children (ignored in post-order,
     * where children have already been visited).
     */
    case SkipChildren;

    case Stop;
}
