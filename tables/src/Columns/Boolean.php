<?php

declare(strict_types=1);

namespace Gcego\Tables\Columns;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Tables\Support\Icons;
use Gcego\Tables\TablesConfig;

/**
 * True/false as an icon with text:
 *
 *     new Boolean('is_published', 'Published')
 *     new Boolean('is_active', 'Status', new Property(Boolean::TrueLabel, 'Active'), new Property(Boolean::FalseLabel, 'Inactive'), new Property(Boolean::ShowLabel, true))
 *
 * Never color alone: the two states use different icon shapes and always
 * carry a text label (visible with ShowLabel, otherwise for screen readers).
 * Null is shown as the placeholder, not as "no".
 */
class Boolean extends Column
{
    public const string TrueLabel = 'true_label';

    public const string FalseLabel = 'false_label';

    public const string TrueIcon = 'true_icon';

    public const string FalseIcon = 'false_icon';

    /** Show the label next to the icon (it is always available to screen readers). */
    public const string ShowLabel = 'show_label';

    public function cellView(): string
    {
        return 'gcego-tables::columns.boolean';
    }

    public function cellData(mixed $value, mixed $record, TablesConfig $config): array
    {
        $state = self::state($value);

        return [
            'state' => $state,
            'text' => $state === null ? null : $this->text($value, $record, $config),
            'icon' => $state === null ? null : ($state ? $this->stringProperty(self::TrueIcon) ?? 'tables.true' : $this->stringProperty(self::FalseIcon) ?? 'tables.false'),
            'tone' => $state === null ? 'neutral' : ($state ? 'success' : 'danger'),
            'show' => $this->get(self::ShowLabel) === true,
        ];
    }

    public function exportValue(mixed $value, mixed $record, TablesConfig $config): string|int|float|null
    {
        return self::state($value) === null ? null : $this->text($value, $record, $config);
    }

    protected function format(mixed $value, mixed $record, TablesConfig $config): ?string
    {
        $state = self::state($value);

        return match ($state) {
            true => Column::translate($this->stringProperty(self::TrueLabel) ?? 'gcego-tables::messages.yes'),
            false => Column::translate($this->stringProperty(self::FalseLabel) ?? 'gcego-tables::messages.no'),
            null => null,
        };
    }

    protected function defaultAlign(): string
    {
        return 'center';
    }

    public static function state(mixed $value): ?bool
    {
        return match (true) {
            $value === null || $value === '' => null,
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
            new PropertyDefinition(self::ShowLabel, Type::bool(), default: false),
        );
    }
}
