<?php

declare(strict_types=1);

namespace Gcego\Navigation\Resolution;

use Gcego\Navigation\Badges\NavigationBadge;
use Gcego\Navigation\NavigationNode;

/**
 * A node as one user sees it in one request: visible, with its URL, active
 * state, badge and visible children. Never cached across users.
 */
final readonly class ResolvedNode
{
    /**
     * @param  list<ResolvedNode>  $children
     */
    public function __construct(
        public string $id,
        public string $type,
        public NavigationNode $component,
        public ?string $label,
        public ?string $url = null,
        public bool $active = false,
        public bool $activeDescendant = false,
        public bool $expanded = true,
        public bool $collapsible = false,
        public ?NavigationBadge $badge = null,
        public array $children = [],
        public int $depth = 1,
    ) {}

    public function isLink(): bool
    {
        return $this->type === 'item' || $this->type === 'external';
    }

    public function isContainer(): bool
    {
        return $this->type === 'group' || $this->type === 'dropdown';
    }

    /**
     * Whether this node is the active page or holds it.
     */
    public function isCurrent(): bool
    {
        return $this->active || $this->activeDescendant;
    }
}
