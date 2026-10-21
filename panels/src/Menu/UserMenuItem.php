<?php

declare(strict_types=1);

namespace Gcego\Panels\Menu;

use Gcego\Components\Component;
use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Security\Contracts\Requirement;
use Gcego\Tables\Support\Icons;

/**
 * A link in a panel's user menu, to an existing named route (profile,
 * account security...). It appears only when the route exists and its
 * requirements (gcego/security nodes) allow:
 *
 *     new UserMenuItem('Account security', 'sentinel.account.security')
 *
 * Profiles, passwords and two-factor authentication are the application's
 * (or gcego/sentinel's) routes; the menu only links to them.
 */
final class UserMenuItem extends Component
{
    public const string Route = 'route';

    public const string Icon = 'icon';

    public function label(): string
    {
        $label = $this->get(self::Label);

        return is_string($label) ? $label : '';
    }

    public function route(): string
    {
        $route = $this->get(self::Route);

        return is_string($route) ? $route : '';
    }

    public function icon(): ?string
    {
        $icon = $this->get(self::Icon);

        return is_string($icon) ? $icon : null;
    }

    /**
     * @return list<Requirement>
     */
    public function requirements(): array
    {
        return array_values(array_filter($this->configurations()->all(), static fn (mixed $node): bool => $node instanceof Requirement));
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('panels.user_menu_item')
            ->withoutProperties(self::Description, self::Id)
            ->withProperties(
                new PropertyDefinition(self::Label, Type::string(), required: true, rule: static fn (mixed $value): bool => is_string($value) && trim($value) !== '' && mb_strlen($value) <= 80, ruleDescription: 'a label of at most 80 characters'),
                new PropertyDefinition(self::Route, Type::string(), required: true, rule: static fn (mixed $value): bool => is_string($value) && preg_match('/^[A-Za-z0-9_.:-]{1,190}$/', $value) === 1, ruleDescription: 'a route name'),
                new PropertyDefinition(self::Icon, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match(Icons::NamePattern, $value) === 1, ruleDescription: 'an icon name'),
            )
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Label, self::Route];
    }
}
