<?php

declare(strict_types=1);

namespace Gcego\Navigation;

use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Contracts\BlueprintPart;
use Gcego\Chroma\Tailwind;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Components\Traversal\ComponentWalker;
use Gcego\Security\Contracts\Requirement;
use Illuminate\Support\Str;

/**
 * A navigation: an ordered tree of items, groups, dropdowns, headings,
 * dividers and external links.
 *
 *     // app/GCEGo/Navigation/Admin.php — discovered as "navigation:admin"
 *     return new Navigation('admin',
 *         new Item('Dashboard', 'admin.dashboard'),
 *         new Group('Academic Management', new Item('Students', 'admin.students.index'), …),
 *     );
 *
 * Render it with NavigationManager (sidebar, topbar, mobile, breadcrumbs),
 * <x-gcego-navigation::navigation name="admin" />, or place it in a
 * Blueprint or layout. It is a blueprint part (role "navigation").
 */
final class Navigation extends NavigationNode implements BlueprintPart
{
    /** How the runtime renders it when placed in a blueprint or layout: sidebar (default), topbar, mobile or breadcrumbs. */
    public const string Display = 'display';

    /** The name of the item breadcrumbs start from (for example "dashboard"). */
    public const string Home = 'home';

    public const array Displays = ['sidebar', 'topbar', 'mobile', 'breadcrumbs'];

    /** Styling slots (defined on Navigation; Tailwind::Wrapper, Icon and Label apply too). */
    public const string ListSlot = 'list';

    public const string ItemSlot = 'item';

    public const string ItemActiveSlot = 'item_active';

    public const string ItemInactiveSlot = 'item_inactive';

    public const string BadgeSlot = 'badge';

    public const string GroupSlot = 'group';

    public const string GroupHeaderSlot = 'group_header';

    public const string GroupItemsSlot = 'group_items';

    public const string HeadingSlot = 'heading';

    public const string DividerSlot = 'divider';

    public const string DropdownSlot = 'dropdown';

    public const string TopbarSlot = 'topbar';

    public const string MobileSlot = 'mobile';

    public const string BreadcrumbSlot = 'breadcrumb';

    public function nodeType(): string
    {
        return 'navigation';
    }

    public function blueprintRole(): BlueprintRole
    {
        return BlueprintRole::multiple(BlueprintRole::Navigation);
    }

    /**
     * The registry name: the Name, or the Label in snake case.
     */
    public function navigationName(): string
    {
        $name = $this->string(self::Name);

        return $name ?? Str::snake(Str::ascii(trim((string) preg_replace('/[^A-Za-z0-9 ]+/', ' ', $this->label() ?? ''))));
    }

    public function display(): string
    {
        return $this->string(self::Display) ?? 'sidebar';
    }

    public function home(): ?string
    {
        return $this->string(self::Home);
    }

    /**
     * @return array<string, string>
     */
    public static function styleSlots(): array
    {
        return [
            Tailwind::Wrapper => 'The <nav> element.',
            self::ListSlot => 'Every list of nodes.',
            self::ItemSlot => 'Every item link.',
            self::ItemActiveSlot => 'The active item link.',
            self::ItemInactiveSlot => 'Other item links.',
            Tailwind::Icon => 'Item icons.',
            Tailwind::Label => 'Item labels.',
            self::BadgeSlot => 'Badges.',
            self::GroupSlot => 'Each group.',
            self::GroupHeaderSlot => 'Group headers and toggles.',
            self::GroupItemsSlot => 'The list of a group.',
            self::HeadingSlot => 'Headings.',
            self::DividerSlot => 'Dividers.',
            self::DropdownSlot => 'Dropdown panels.',
            self::TopbarSlot => 'The top navigation list.',
            self::MobileSlot => 'The mobile drawer panel.',
            self::BreadcrumbSlot => 'The breadcrumb list.',
        ];
    }

    protected static function shorthand(): array
    {
        return [self::Name];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $value) === 1, ruleDescription: 'a lowercase name such as "admin"'),
                new PropertyDefinition(self::Display, Type::string(), default: 'sidebar', allowedValues: self::Displays),
                new PropertyDefinition(self::Home, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match(self::NamePattern, $value) === 1, ruleDescription: 'an item name'),
            )
            ->withAllowedChildren([NavigationNode::class])
            ->withConstraint(static function (ComponentInterface $navigation): array {
                $errors = [];
                $seen = [];

                foreach (app(ComponentWalker::class)->preOrder($navigation) as $node) {
                    if ($node->isRoot()) {
                        continue;
                    }

                    if ($node->component instanceof self) {
                        $errors[] = sprintf('a navigation cannot contain another navigation (at "%s").', $node->path->toString());

                        continue;
                    }

                    $name = $node->component instanceof NavigationNode ? $node->component->nodeName() : '';

                    if ($name !== '' && isset($seen[$name])) {
                        $errors[] = sprintf('two nodes are named "%s" (at "%s" and "%s").', $name, $seen[$name], $node->path->toString());
                    }

                    $seen[$name] ??= $node->path->toString();
                }

                return $errors;
            });
    }

    protected static function nodeConfigurations(): array
    {
        return [Requirement::class, Tailwind::class];
    }
}
