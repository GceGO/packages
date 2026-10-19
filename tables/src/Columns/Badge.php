<?php

declare(strict_types=1);

namespace Gcego\Tables\Columns;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Luminaries\IconName;
use Gcego\Tables\Presets\TablePresets;
use Gcego\Tables\Support\Icons;
use Gcego\Tables\TablesConfig;

/**
 * A status pill with a semantic variant:
 *
 *     new Badge('status', 'Status',
 *         new Property(Badge::Variants, ['published' => 'success', 'draft' => 'warning', 'archived' => 'neutral']),
 *         new Property(Badge::Labels, ['published' => 'Published', 'draft' => 'Draft']),
 *     )
 *
 * Variants: default, success, warning, danger, info, neutral. The badge
 * always shows text, so the meaning never depends on color.
 */
class Badge extends Column
{
    /** value => variant */
    public const string Variants = 'variants';

    /** value => label (translated) */
    public const string Labels = 'labels';

    /** value => icon name from the trusted set */
    public const string Icons = 'icons';

    /** Variant for values not in Variants. */
    public const string DefaultVariant = 'default_variant';

    public function cellView(): string
    {
        return 'gcego-tables::columns.badge';
    }

    public function cellData(mixed $value, mixed $record, TablesConfig $config): array
    {
        $variant = $this->lookup(self::Variants, $value);
        $icon = IconName::tryOf($this->lookup(self::Icons, $value));

        return [
            ...parent::cellData($value, $record, $config),
            'variant' => is_string($variant) && isset(TablePresets::Variants[$variant]) ? $variant : ($this->stringProperty(self::DefaultVariant) ?? 'neutral'),
            'icon' => is_string($icon) ? $icon : null,
        ];
    }

    protected function format(mixed $value, mixed $record, TablesConfig $config): ?string
    {
        $label = $this->lookup(self::Labels, $value);

        return is_string($label) ? (string) __($label) : Column::stringify($value);
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $variants = array_keys(TablePresets::Variants);
        $map = static fn (\Closure $valid): \Closure => static fn (mixed $value): bool => is_array($value) && array_filter($value, static fn (mixed $item): bool => ! $valid($item)) === [];

        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Variants, Type::array(), default: [], rule: $map(static fn (mixed $item): bool => is_string($item) && in_array($item, $variants, true)), ruleDescription: 'a map of values to default, success, warning, danger, info or neutral'),
            new PropertyDefinition(self::Labels, Type::array(), default: [], rule: $map(is_string(...)), ruleDescription: 'a map of values to labels'),
            new PropertyDefinition(self::Icons, Type::array(), default: [], rule: $map(IconName::accepts(...)), ruleDescription: 'a map of values to icon names'),
            new PropertyDefinition(self::DefaultVariant, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && in_array($value, $variants, true), ruleDescription: 'a variant'),
        );
    }
}
