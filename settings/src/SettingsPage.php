<?php

declare(strict_types=1);

namespace Gcego\Settings;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Luminaries\IconName;
use Gcego\Settings\Definitions\SettingsNode;
use Gcego\Settings\Scopes\SettingScope;

/**
 * A settings screen in every gcego/panels panel ("<panel>/settings/<name>"):
 *
 *     return new SettingsPage(name: 'general', title: 'General Settings', group: 'general');
 *     return new SettingsPage(name: 'school', title: 'School settings', group: 'institution', scope: SettingScope::Tenant);
 *
 * A gcego/registry definition of kind "settings-page", discovered from
 * app/GCEGo/SettingsPages. It edits one group at one scope; the scope's
 * identity always comes from the server (the request's verified tenant,
 * panel or user), never from the browser. Groups without a page get a
 * default one at the first scope they allow. Panels limits the panels it
 * appears in (default: all). Requirements given as items are needed to
 * open it, on top of the settings' own.
 */
final class SettingsPage extends SettingsNode
{
    public const string Title = 'label';

    public const string Group = 'group';

    /** The scope this page edits (a SettingScope or its value, default global). */
    public const string Scope = 'scope';

    public const string Icon = 'icon';

    public const string Sort = 'sort';

    /** Panel names showing the page (default: every panel). */
    public const string Panels = 'panels';

    public function pageName(): string
    {
        return $this->nodeName();
    }

    public function title(): string
    {
        return $this->label();
    }

    public function groupName(): string
    {
        return $this->text(self::Group) ?? '';
    }

    public function scope(): SettingScope
    {
        $scope = $this->get(self::Scope);

        return $scope instanceof SettingScope ? $scope : (is_string($scope) ? SettingScope::tryFrom($scope) : null) ?? SettingScope::Global;
    }

    public function icon(): ?string
    {
        return $this->text(self::Icon);
    }

    public function sort(): int
    {
        return $this->integer(self::Sort) ?? 0;
    }

    /**
     * @return list<string>
     */
    public function panels(): array
    {
        return $this->strings(self::Panels);
    }

    public function appearsIn(?string $panel): bool
    {
        return $this->panels() === [] || ($panel !== null && in_array($panel, $this->panels(), true));
    }

    protected static function namedArguments(): array
    {
        return [...parent::namedArguments(), 'title' => self::Title, 'group' => self::Group, 'scope' => self::Scope, 'icon' => self::Icon, 'sort' => self::Sort, 'panels' => self::Panels];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('settings.page')
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: self::validName(...), ruleDescription: 'a lowercase name such as "general"'),
                new PropertyDefinition(self::Label, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && trim($value) !== '' && mb_strlen($value) <= 120, ruleDescription: 'a title of at most 120 characters'),
                new PropertyDefinition(self::Group, Type::string(), required: true, rule: self::validName(...), ruleDescription: 'a settings group name'),
                new PropertyDefinition(self::Scope, [Type::string(), Type::enum(SettingScope::class)], allowedValues: [...array_map(static fn (SettingScope $scope): string => $scope->value, SettingScope::cases()), ...SettingScope::cases()]),
                new PropertyDefinition(self::Icon, Type::string(), rule: IconName::accepts(...), ruleDescription: 'an icon name'),
                new PropertyDefinition(self::Sort, Type::int()),
                new PropertyDefinition(self::Panels, Type::list(), rule: static fn (mixed $value): bool => is_array($value) && array_filter($value, static fn (mixed $panel): bool => ! self::validName($panel)) === [], ruleDescription: 'a list of panel names'),
            )
            ->withoutChildren();
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }
}
