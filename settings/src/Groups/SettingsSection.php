<?php

declare(strict_types=1);

namespace Gcego\Settings\Groups;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Settings\Definitions\SettingsNode;
use Gcego\Settings\Setting;

/**
 * A titled part of a settings group (a gcego/forms Section, laid out in a Grid):
 *
 *     new SettingsSection('mail', 'Outgoing mail',
 *         new Setting('mail_enabled', false),
 *         new Setting('mail_from_address', '', rules: ['email']),
 *         columns: 2,
 *     )
 *
 * Requirements given as items apply to the section's settings, on top of the group's.
 */
final class SettingsSection extends SettingsNode
{
    public const string Title = 'label';

    /** Grid columns (1 to 4, default 1). */
    public const string Columns = 'columns';

    public const string Sort = 'sort';

    public function columns(): int
    {
        return $this->integer(self::Columns) ?? 1;
    }

    public function sort(): int
    {
        return $this->integer(self::Sort) ?? 0;
    }

    /**
     * @return list<Setting>
     */
    public function settings(): array
    {
        return $this->childrenOf(Setting::class);
    }

    protected static function namedArguments(): array
    {
        return [...parent::namedArguments(), 'title' => self::Title, 'columns' => self::Columns, 'sort' => self::Sort];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()
            ->withType('settings.section')
            ->withoutProperties(self::Id)
            ->withProperties(
                new PropertyDefinition(self::Name, Type::string(), required: true, rule: self::validName(...), ruleDescription: 'a lowercase name such as "mail"'),
                new PropertyDefinition(self::Columns, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 1 && $value <= 4, ruleDescription: 'between 1 and 4'),
                new PropertyDefinition(self::Sort, Type::int()),
            )
            ->withAllowedChildren([Setting::class]);
    }

    protected static function shorthand(): array
    {
        return [self::Name, self::Label];
    }
}
