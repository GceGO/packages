<?php

declare(strict_types=1);

namespace Gcego\Components\Traversal;

enum TraversalOrder
{
    /**
     * Parent before children, depth first.
     */
    case PreOrder;

    /**
     * Children before parent, depth first.
     */
    case PostOrder;

    /**
     * Level by level, left to right.
     */
    case BreadthFirst;
}
