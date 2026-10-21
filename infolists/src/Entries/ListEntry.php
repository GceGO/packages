<?php

declare(strict_types=1);

namespace Gcego\Infolists\Entries;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Infolists\InfolistsConfig;

/**
 * A list of values: an array attribute, or a to-many relation path:
 *
 *     new ListEntry('tags.label', 'Tags')                                         // eager-loaded relation
 *     new ListEntry('languages', 'Languages', new Property(ListEntry::Style, 'badges'))
 *     new ListEntry('steps', 'Steps', new Property(ListEntry::Style, 'numbered'))
 *
 * Styles: bulleted (default), numbered, badges, inline. At most
 * lists.max_items items are shown, with a "+N more" note.
 */
class ListEntry extends Entry
{
    public const string Style = 'style';

    public const array Styles = ['bulleted', 'numbered', 'badges', 'inline'];

    public function view(): string
    {
        return 'gcego-infolists::entries.list';
    }

    public function display(mixed $value, mixed $record, InfolistsConfig $config): array
    {
        $items = [];
        $total = 0;

        foreach (is_iterable($value) ? $value : ($value === null ? [] : [$value]) as $item) {
            $text = $this->text($item, $record, $config);

            if ($text === null) {
                continue;
            }

            $total++;

            if (count($items) < $config->maxListItems) {
                $items[] = $text;
            }
        }

        return [
            'items' => $items,
            'more' => $total - count($items),
            'style' => $this->stringProperty(self::Style) ?? 'bulleted',
            'text' => $items === [] ? null : implode(', ', $items),
            'title' => null,
            'copy' => false,
        ];
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Style, Type::string(), default: 'bulleted', allowedValues: self::Styles),
        );
    }
}
