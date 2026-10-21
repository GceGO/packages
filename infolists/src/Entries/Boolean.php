<?php

declare(strict_types=1);

namespace Gcego\Infolists\Entries;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Infolists\InfolistsConfig;
use Gcego\Tables\Support\Formats;
use Gcego\Tables\Support\Icons;

/**
 * Yes/No with an icon and visible text (never color alone):
 *
 *     new Boolean('is_published', 'Published')
 *     new Boolean('is_active', 'Status', new Property(Boolean::TrueLabel, 'Enabled'), new Property(Boolean::FalseLabel, 'Disabled'))
 *
 * Null shows the placeholder, not "No".
 */
class Boolean extends Entry
{
    public const string TrueLabel = 'true_label';

    public const string FalseLabel = 'false_label';

    public const string TrueIcon = 'true_icon';

    public const string FalseIcon = 'false_icon';

    public function view(): string
    {
        return 'gcego-infolists::entries.boolean';
    }

    public function display(mixed $value, mixed $record, InfolistsConfig $config): array
    {
        $state = self::state($value);

        return [
            'state' => $state,
            'text' => $state === null ? null : $this->text($value, $record, $config),
            'icon' => $state === null ? null : ($state ? $this->stringProperty(self::TrueIcon) ?? 'infolists.true' : $this->stringProperty(self::FalseIcon) ?? 'infolists.false'),
            'tone' => $state === null ? 'neutral' : ($state ? 'success' : 'danger'),
            'title' => null,
            'copy' => false,
        ];
    }

    protected function format(mixed $value, mixed $record, InfolistsConfig $config): ?string
    {
        return match (self::state($value)) {
            true => Formats::translate($this->stringProperty(self::TrueLabel) ?? 'gcego-tables::messages.yes'),
            false => Formats::translate($this->stringProperty(self::FalseLabel) ?? 'gcego-tables::messages.no'),
            null => null,
        };
    }

    public static function state(mixed $value): ?bool
    {
        return match (true) {
            $value === true, $value === 1, $value === '1', $value === 'true' => true,
            $value === false, $value === 0, $value === '0', $value === 'false' => false,
            default => null,
        };
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $icon = static fn (mixed $value): bool => is_string($value) && preg_match(Icons::NamePattern, $value) === 1;

        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::TrueLabel, Type::string()),
            new PropertyDefinition(self::FalseLabel, Type::string()),
            new PropertyDefinition(self::TrueIcon, Type::string(), rule: $icon, ruleDescription: 'an icon name'),
            new PropertyDefinition(self::FalseIcon, Type::string(), rule: $icon, ruleDescription: 'an icon name'),
        );
    }
}
