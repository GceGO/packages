<?php

declare(strict_types=1);

namespace Gcego\Tables\Columns;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Luminaries\IconName;
use Gcego\Tables\Presets\TablePresets;
use Gcego\Tables\Support\Icons;
use Gcego\Tables\TablesConfig;

/**
 * An icon chosen by value from the trusted icon set:
 *
 *     new Icon('priority', 'Priority',
 *         new Property(Icon::Icons, ['high' => 'exclamation-triangle', 'normal' => 'minus']),
 *         new Property(Icon::Tones, ['high' => 'danger']),
 *         new Property(Icon::Labels, ['high' => 'High priority', 'normal' => 'Normal']),
 *     )
 *
 * Only names from the icon set render (a name from the database that is not
 * registered renders nothing), and each icon carries an accessible label
 * (from Labels, or the value itself).
 */
class Icon extends Column
{
    public const string Icons = 'icons';

    public const string Tones = 'tones';

    public const string Labels = 'labels';

    public function cellView(): string
    {
        return 'gcego-tables::columns.icon';
    }

    public function cellData(mixed $value, mixed $record, TablesConfig $config): array
    {
        $icon = IconName::tryOf($this->lookup(self::Icons, $value)) ?? ($this->mapProperty(self::Icons) === [] ? IconName::tryOf($value) : null);
        $tone = $this->lookup(self::Tones, $value);

        return [
            'icon' => is_string($icon) && app(Icons::class)->has($icon) ? $icon : null,
            'tone' => is_string($tone) && isset(TablePresets::IconTones[$tone]) ? $tone : 'neutral',
            'text' => $this->text($value, $record, $config),
        ];
    }

    protected function format(mixed $value, mixed $record, TablesConfig $config): ?string
    {
        $label = $this->lookup(self::Labels, $value);

        return is_string($label) ? (string) __($label) : Column::stringify($value);
    }

    protected function defaultAlign(): string
    {
        return 'center';
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $map = static fn (\Closure $valid): \Closure => static fn (mixed $value): bool => is_array($value) && array_filter($value, static fn (mixed $item): bool => ! $valid($item)) === [];

        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Icons, Type::array(), default: [], rule: $map(IconName::accepts(...)), ruleDescription: 'a map of values to icon names'),
            new PropertyDefinition(self::Tones, Type::array(), default: [], rule: $map(static fn (mixed $item): bool => is_string($item) && isset(TablePresets::IconTones[$item])), ruleDescription: 'a map of values to default, success, warning, danger, info or neutral'),
            new PropertyDefinition(self::Labels, Type::array(), default: [], rule: $map(is_string(...)), ruleDescription: 'a map of values to labels'),
        );
    }
}
