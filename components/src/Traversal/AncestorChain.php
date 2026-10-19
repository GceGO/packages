<?php

declare(strict_types=1);

namespace Gcego\Components\Traversal;

/**
 * A persistent linked list of ancestor object ids used for cycle detection.
 *
 * Siblings share their parent's chain, so tracking ancestors costs constant
 * memory per visited node.
 *
 * @internal
 */
final readonly class AncestorChain
{
    public function __construct(
        private int $objectId,
        private ?self $parent = null,
    ) {}

    public function contains(int $objectId): bool
    {
        for ($chain = $this; $chain !== null; $chain = $chain->parent) {
            if ($chain->objectId === $objectId) {
                return true;
            }
        }

        return false;
    }
}
