<?php

declare(strict_types=1);

namespace Gcego\Tables\Columns;

use Gcego\Components\Building\ComponentBuilder;
use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Forms\Fields\TextInput;
use Gcego\Infolists\Entries\Text as TextEntry;
use Gcego\Tables\Support\ValueResolver;
use Gcego\Tables\TablesConfig;

/**
 * Plain, escaped text, and the entry point for text in every context:
 *
 *     Text::define('name', 'Subject Name')->searchable()   // table column
 *     Text::input('name', 'Subject Name')->required()      // form field (gcego/forms TextInput)
 *     Text::display('name', 'Subject Name')                // infolist entry (gcego/infolists Text)
 *
 *     new Text('name', 'Subject Name')
 *     new Text('description', new Property(Text::Limit, 60), new Property(Text::Wrap, true))
 *     new Text('code', new Property(Text::Copyable, true), new Property(Text::Prefix, '#'))
 */
class Text extends Column
{
    /** Adds a copy-to-clipboard button for the cell's text. */
    public const string Copyable = 'copyable';

    public const string Prefix = 'prefix';

    public const string Suffix = 'suffix';

    /** Small secondary text under the value: a relation path or column of the row. */
    public const string DescriptionColumn = 'description_column';

    /**
     * A form text input (gcego/forms TextInput) with the same arguments as define().
     */
    public static function input(mixed ...$items): ComponentBuilder
    {
        return TextInput::define(...$items);
    }

    /**
     * An infolist text entry (gcego/infolists Text) with the same arguments as define().
     */
    public static function display(mixed ...$items): ComponentBuilder
    {
        return TextEntry::define(...$items);
    }

    public function cellView(): string
    {
        return 'gcego-tables::columns.text';
    }

    public function cellData(mixed $value, mixed $record, TablesConfig $config): array
    {
        $description = $this->stringProperty(self::DescriptionColumn);

        return [
            ...parent::cellData($value, $record, $config),
            'copy' => $this->get(self::Copyable) === true,
            'description' => $description === null ? null : Column::stringify(ValueResolver::get($record, $description)),
        ];
    }

    protected function format(mixed $value, mixed $record, TablesConfig $config): ?string
    {
        $text = Column::stringify($value);

        return $text === null ? null : ($this->stringProperty(self::Prefix) ?? '').$text.($this->stringProperty(self::Suffix) ?? '');
    }

    /**
     * The secondary relation to eager-load, when the description reads through one.
     *
     * @return list<string>
     */
    public function relations(): array
    {
        $description = $this->stringProperty(self::DescriptionColumn);

        return array_values(array_filter([
            $this->relation(),
            $description !== null && str_contains($description, '.') ? substr($description, 0, (int) strrpos($description, '.')) : null,
        ]));
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Copyable, Type::bool(), default: false),
            new PropertyDefinition(self::Prefix, Type::string()),
            new PropertyDefinition(self::Suffix, Type::string()),
            new PropertyDefinition(self::DescriptionColumn, Type::string(), rule: static fn (mixed $value): bool => is_string($value) && preg_match(Column::NamePattern, $value) === 1, ruleDescription: 'a column name or relation path'),
        );
    }
}
