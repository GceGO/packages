<?php

declare(strict_types=1);

namespace Gcego\Infolists\Entries;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Infolists\InfolistsConfig;
use Gcego\Luminaries\IconName;
use Gcego\Tables\Presets\TablePresets;
use Gcego\Tables\Support\Formats;
use Gcego\Tables\Support\Icons;

/**
 * An icon chosen by value from the trusted icon set (gcego/tables Support\Icons):
 *
 *     new Icon('priority', 'Priority',
 *         new Property(Icon::Icons, ['high' => 'exclamation-triangle', 'normal' => 'minus']),
 *         new Property(Icon::Tones, ['high' => 'danger']),
 *         new Property(Icon::Labels, ['high' => 'High priority', 'normal' => 'Normal']),
 *         new Property(Icon::Size, 'lg'),
 *     )
 *
 * The label is shown next to the icon unless ShowLabel is false (it then
 * stays available to screen readers). Unregistered names render the text only.
 */
class Icon extends Entry
{
    public const string Icons = 'icons';

    public const string Tones = 'tones';

    public const string Labels = 'labels';

    /** sm | md | lg */
    public const string Size = 'size';

    public const string ShowLabel = 'show_label';

    public const array Sizes = ['sm' => 'size-4', 'md' => 'size-5', 'lg' => 'size-7'];

    public function view(): string
    {
        return 'gcego-infolists::entries.icon';
    }

    public function display(mixed $value, mixed $record, InfolistsConfig $config): array
    {
        $icon = IconName::tryOf($this->lookup(self::Icons, $value)) ?? ($this->mapProperty(self::Icons) === [] ? IconName::tryOf($value) : null);
        $tone = $this->lookup(self::Tones, $value);
        $size = $this->stringProperty(self::Size) ?? 'md';

        return [
            'icon' => is_string($icon) && app(Icons::class)->has($icon) ? $icon : null,
            'tone' => is_string($tone) && isset(TablePresets::IconTones[$tone]) ? $tone : 'neutral',
            'size' => self::Sizes[$size] ?? 'size-5',
            'text' => $this->text($value, $record, $config),
            'show' => $this->get(self::ShowLabel) !== false,
            'title' => null,
            'copy' => false,
        ];
    }

    protected function format(mixed $value, mixed $record, InfolistsConfig $config): ?string
    {
        $label = $this->lookup(self::Labels, $value);

        return is_string($label) ? Formats::translate($label) : Formats::text($value);
    }

    protected static function defineSchema(): ComponentDefinition
    {
        $map = static fn (\Closure $valid): \Closure => static fn (mixed $value): bool => is_array($value) && array_filter($value, static fn (mixed $item): bool => ! $valid($item)) === [];

        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Icons, Type::array(), default: [], rule: $map(IconName::accepts(...)), ruleDescription: 'a map of values to icon names'),
            new PropertyDefinition(self::Tones, Type::array(), default: [], rule: $map(static fn (mixed $item): bool => is_string($item) && isset(TablePresets::IconTones[$item])), ruleDescription: 'a map of values to tones'),
            new PropertyDefinition(self::Labels, Type::array(), default: [], rule: $map(is_string(...)), ruleDescription: 'a map of values to labels'),
            new PropertyDefinition(self::Size, Type::string(), default: 'md', allowedValues: array_keys(self::Sizes)),
            new PropertyDefinition(self::ShowLabel, Type::bool(), default: true),
        );
    }
}
