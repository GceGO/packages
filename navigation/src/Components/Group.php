<?php

declare(strict_types=1);

namespace Gcego\Navigation\Components;

use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Navigation\Navigation;
use Gcego\Navigation\NavigationNode;

/**
 * A labelled group of nodes:
 *
 *     new Group('Academic Management',
 *         new Item('Students', 'admin.students.index'),
 *         new Item('Subjects', 'admin.subjects.index'),
 *     )
 *
 * In the sidebar it is a section whose header toggles it (when collapsible),
 * expanded by default and always while it holds the active page; in the top
 * navigation it is a dropdown. A group the user can see nothing in is not
 * shown. Groups nest up to gcego-navigation.groups.max_depth levels.
 */
class Group extends NavigationNode
{
    /** Start collapsed (unless it holds the active page). */
    public const string Collapsed = 'collapsed';

    /** Whether the header toggles the group (default: gcego-navigation.groups.collapsible). */
    public const string Collapsible = 'collapsible';

    public function nodeType(): string
    {
        return 'group';
    }

    public function isCollapsed(): bool
    {
        return $this->get(self::Collapsed) === true;
    }

    public function collapsible(): ?bool
    {
        $collapsible = $this->get(self::Collapsible);

        return is_bool($collapsible) ? $collapsible : null;
    }

    protected static function shorthand(): array
    {
        return [self::Label];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withProperties(
                new PropertyDefinition(self::Label, Type::string(), required: true),
                new PropertyDefinition(self::Collapsed, Type::bool(), default: false),
                new PropertyDefinition(self::Collapsible, Type::bool()),
                self::iconProperty(),
            )
            ->withAllowedChildren([NavigationNode::class], 1)
            ->withConstraint(static fn (ComponentInterface $group): ?string => array_filter($group->children()->all(), static fn ($child): bool => $child instanceof Navigation) !== [] ? 'a navigation cannot be nested in a group.' : null);
    }
}
