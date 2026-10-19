<?php

declare(strict_types=1);

namespace Gcego\Navigation\Tree;

use Gcego\Navigation\Navigation;
use Gcego\Security\Contracts\Requirement;

/**
 * A validated, ordered navigation structure. It holds no user, tenant or
 * request data, so one instance serves every request (it is cached per
 * definition in the process).
 */
final readonly class NavigationTree
{
    /**
     * @param  list<Node>  $nodes  Sorted root nodes.
     * @param  list<Requirement>  $requirements  The Navigation's own requirements.
     */
    public function __construct(
        public string $identity,
        public Navigation $navigation,
        public array $nodes,
        public array $requirements,
    ) {}

    /**
     * Every node, depth first in display order.
     *
     * @return list<Node>
     */
    public function all(): array
    {
        return self::flatten($this->nodes);
    }

    /**
     * @param  list<Node>  $nodes
     * @return list<Node>
     */
    private static function flatten(array $nodes): array
    {
        $all = [];

        foreach ($nodes as $node) {
            $all[] = $node;
            array_push($all, ...self::flatten($node->children));
        }

        return $all;
    }

    public function find(string $id): ?Node
    {
        foreach ($this->all() as $node) {
            if ($node->id === $id) {
                return $node;
            }
        }

        return null;
    }

    public function count(): int
    {
        return count($this->all());
    }
}
