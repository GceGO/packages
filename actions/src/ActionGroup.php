<?php

declare(strict_types=1);

namespace Gcego\Actions;

use Gcego\Blueprints\BlueprintRole;
use Gcego\Blueprints\Contracts\PartContainer;
use Gcego\Components\Component;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Luminaries\IconName;

/**
 * Groups actions under one label (rendered as a menu), in declaration order:
 *
 *     new ActionGroup('more', 'More', new Edit, new Delete)
 *
 * The group only arranges. Every child keeps its own authorization,
 * confirmation and visibility; an allowed group never allows its children.
 */
final class ActionGroup extends Component implements PartContainer
{
    public const string Icon = 'icon';

    public const string Placement = 'placement';

    public function blueprintRole(): BlueprintRole
    {
        return BlueprintRole::multiple(BlueprintRole::Action);
    }

    /**
     * @return list<Action>
     */
    public function actions(): array
    {
        return array_values(array_filter($this->children()->all(), static fn (mixed $child): bool => $child instanceof Action));
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('action.group')
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: static fn (mixed $value): bool => is_string($value) && preg_match(Action::NamePattern, $value) === 1, ruleDescription: 'a lowercase group name'),
                new PropertyDefinition(self::Icon, Type::string(), default: 'actions.more-vertical', rule: IconName::accepts(...), ruleDescription: 'an icon name'),
                new PropertyDefinition(self::Placement, [Type::string(), Type::list()]),
            )
            ->withoutProperties(self::Id)
            ->withAllowedChildren([Action::class], min: 1);
    }

    /**
     * @return list<string>
     */
    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }
}
