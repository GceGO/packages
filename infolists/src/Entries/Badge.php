<?php

declare(strict_types=1);

namespace Gcego\Infolists\Entries;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Infolists\InfolistsConfig;
use Gcego\Luminaries\IconName;
use Gcego\Tables\Presets\TablePresets;
use Gcego\Tables\Support\Formats;
use Gcego\Tables\Support\Icons;

/**
 * A status pill. Values map to trusted variants, never to CSS classes:
 *
 *     new Badge('status', 'Status')                                                     // neutral
 *     new Badge('status', 'Status',
 *         new Property(Badge::Variants, ['published' => 'success', 'draft' => 'warning', 'archived' => 'neutral']),
 *         new Property(Badge::Labels, ['published' => 'Published']),
 *         new Property(Badge::Icons, ['published' => 'check-circle']),
 *     )
 *
 * Variants: default, success, warning, danger, info, neutral. Enums with a
 * label() method show it. The badge always shows text.
 */
class Badge extends Entry
{
    public const string Variants = 'variants';

    public const string Labels = 'labels';

    public const string Icons = 'icons';

    public const string DefaultVariant = 'default_variant';

    public function view(): string
    {
        return 'gcego-infolists::entries.badge';
    }

    public function display(mixed $value, mixed $record, InfolistsConfig $config): array
    {
        $items = [];

        foreach (is_iterable($value) ? $value : [$value] as $item) {
            $text = $this->text($item, $record, $config);

            if ($text !== null) {
                $variant = $this->lookup(self::Variants, $item);
                $icon = IconName::tryOf($this->lookup(self::Icons, $item));
                $items[] = [
                    'text' => $text,
                    'variant' => is_string($variant) && isset(TablePresets::Variants[$variant]) ? $variant : ($this->stringProperty(self::DefaultVariant) ?? 'neutral'),
                    'icon' => is_string($icon) ? $icon : null,
                ];
            }
        }

        return ['items' => $items, 'text' => $items === [] ? null : implode(', ', array_column($items, 'text')), 'title' => null, 'copy' => false];
    }

    protected function format(mixed $value, mixed $record, InfolistsConfig $config): ?string
    {
        $label = $this->lookup(self::Labels, $value);

        return is_string($label) ? Formats::translate($label) : Formats::text($value);
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
