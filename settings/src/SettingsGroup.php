<?php

declare(strict_types=1);

namespace Gcego\Settings;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\Contracts\ComponentInterface;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Luminaries\IconName;
use Gcego\Settings\Definitions\SettingsDefinitionValidator;
use Gcego\Settings\Definitions\SettingsNode;
use Gcego\Settings\Groups\SettingsSection;
use Gcego\Settings\Scopes\SettingScope;

/**
 * A named set of settings, declared once in PHP:
 *
 *     return new SettingsGroup(
 *         'general',
 *         new Setting('site_name', 'GCEGO'),
 *         new Setting('site_description', ''),
 *         new SettingsSection('support', 'Support', new Setting('support_email', '', rules: ['email'])),
 *     );
 *
 * A gcego/registry definition of kind "settings" ("settings:general"),
 * discovered from app/GCEGo/Settings. Scopes and Approval are defaults
 * for the group's settings. Who may change them: the group's (and each
 * setting's) gcego/security requirements, the "gcego-settings.manage"
 * Gate ability for global and panel values, or the tenant permission
 * "settings.manage" for a tenant's own values. Construction never queries.
 */
final class SettingsGroup extends SettingsNode
{
    public const string Title = 'label';

    public const string Icon = 'icon';

    /** Order among groups in settings navigation. */
    public const string Sort = 'sort';

    /** Default scopes of the group's settings (default: global only). */
    public const string Scopes = 'scopes';

    /** Default approval workflow of the group's settings. */
    public const string Approval = 'approval';

    /** Not listed in settings navigation. */
    public const string Hidden = 'hidden';

    public function groupName(): string
    {
        return $this->nodeName();
    }

    public function title(): string
    {
        return $this->label();
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
     * @return list<SettingScope>
     */
    public function scopes(): array
    {
        return $this->hasExplicit(self::Scopes) ? SettingScope::list($this->strings(self::Scopes)) : [SettingScope::Global];
    }

    public function approval(): ?string
    {
        return $this->text(self::Approval);
    }

    public function isHidden(): bool
    {
        return $this->get(self::Hidden) === true;
    }

    /**
     * @return list<SettingsSection>
     */
    public function sections(): array
    {
        return $this->childrenOf(SettingsSection::class);
    }

    /**
     * Every setting of the group, sections included, in declaration order.
     *
     * @return list<Setting>
     */
    public function settings(): array
    {
        $settings = [];

        foreach ($this->children() as $child) {
            if ($child instanceof Setting) {
                $settings[] = $child;
            } elseif ($child instanceof SettingsSection) {
                array_push($settings, ...$child->settings());
            }
        }

        return $settings;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_map(static fn (Setting $setting): string => $setting->key(), $this->settings());
    }

    protected static function namedArguments(): array
    {
        return [...parent::namedArguments(), 'title' => self::Title, 'icon' => self::Icon, 'sort' => self::Sort, 'scopes' => self::Scopes, 'approval' => self::Approval, 'hidden' => self::Hidden];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('settings.group')
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: self::validName(...), ruleDescription: 'a lowercase name such as "general"'),
                new PropertyDefinition(self::Label, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && trim($value) !== '' && mb_strlen($value) <= 120, ruleDescription: 'a title of at most 120 characters'),
                new PropertyDefinition(self::Icon, Type::string(), rule: IconName::accepts(...), ruleDescription: 'an icon name'),
                new PropertyDefinition(self::Sort, Type::int()),
                new PropertyDefinition(self::Scopes, Type::list(), rule: static fn (mixed $value): bool => is_array($value) && $value !== [] && array_filter($value, static fn (mixed $scope): bool => ! is_string($scope) || SettingScope::tryFrom($scope) === null) === [], ruleDescription: 'scopes among global, panel, tenant and user'),
                new PropertyDefinition(self::Approval, Type::string(), rule: self::validName(...), ruleDescription: 'a workflow name'),
                new PropertyDefinition(self::Hidden, Type::bool(), default: false),
            )
            ->withAllowedChildren([Setting::class, SettingsSection::class])
            ->withConstraint(static fn (ComponentInterface $group): array => $group instanceof self ? SettingsDefinitionValidator::problemsOf($group) : []);
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }
}
