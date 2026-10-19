<?php

declare(strict_types=1);

namespace Gcego\Navigation\Resolution;

use Gcego\Navigation\Tree\NavigationTree;

/**
 * A navigation as the current user sees it on the current page.
 */
final readonly class ResolvedNavigation
{
    /**
     * @param  list<ResolvedNode>  $nodes
     * @param  list<ResolvedNode>  $trail  From the outermost group down to the active item.
     */
    public function __construct(
        public NavigationTree $tree,
        public NavigationContext $context,
        public array $nodes,
        public ?ResolvedNode $active,
        public array $trail,
    ) {}

    public function isEmpty(): bool
    {
        return $this->nodes === [];
    }

    public function identity(): string
    {
        return $this->tree->identity;
    }

    public function label(): ?string
    {
        return $this->tree->navigation->label();
    }

    public function find(string $id): ?ResolvedNode
    {
        return self::search($this->nodes, $id);
    }

    /**
     * @param  list<ResolvedNode>  $nodes
     */
    private static function search(array $nodes, string $id): ?ResolvedNode
    {
        foreach ($nodes as $node) {
            if ($node->id === $id) {
                return $node;
            }

            $found = self::search($node->children, $id);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
