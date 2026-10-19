<?php

declare(strict_types=1);

namespace Gcego\Cli\Generation;

use Illuminate\Support\Str;

/**
 * Turns model fields into gcego/forms fields, gcego/tables columns and
 * gcego/infolists entries (source code plus the imports it needs).
 *
 * | type     | form                       | table          | infolist       |
 * | string   | TextInput (MaxLength)      | Text           | Text           |
 * | text     | Textarea                   | (not listed)   | Text multiline |
 * | boolean  | Toggle                     | Boolean        | Boolean        |
 * | date     | DatePicker                 | Date           | Date           |
 * | datetime | DateTimePicker             | DateTime       | DateTime       |
 * | integer  | NumberInput (Integer)      | Number         | Number         |
 * | decimal  | NumberInput                | Number         | Number         |
 * | enum     | Select (Options of enum)   | Text           | Text           |
 */
final class FieldCode
{
    /** Columns a generated table shows at most. */
    public const int TableColumns = 6;

    /**
     * @param  list<ModelField>  $fields
     * @return array{code: string, imports: list<string>}
     */
    public function form(array $fields, int $indent = 2): array
    {
        $imports = ['Gcego\\Components\\Property', 'Gcego\\Forms\\Form'];
        $lines = [];

        foreach ($fields as $field) {
            if (! $field->fillable || ! $this->safe($field->name)) {
                continue;
            }

            [$class, $import] = match ($field->type) {
                'text' => ['Textarea', 'Gcego\\Forms\\Fields\\Textarea'],
                'boolean' => ['Toggle', 'Gcego\\Forms\\Fields\\Toggle'],
                'date' => ['DatePicker', 'Gcego\\Forms\\Fields\\DatePicker'],
                'datetime' => ['DateTimePicker', 'Gcego\\Forms\\Fields\\DateTimePicker'],
                'integer', 'decimal' => ['NumberInput', 'Gcego\\Forms\\Fields\\NumberInput'],
                'enum' => ['Select', 'Gcego\\Forms\\Fields\\Select'],
                default => ['TextInput', 'Gcego\\Forms\\Fields\\TextInput'],
            };
            $imports[] = $import;
            $parts = [$this->quote($field->name), $this->quote($this->label($field->name))];

            if ($field->required && $field->type !== 'boolean') {
                $parts[] = sprintf('new Property(%s::Required, true)', $class);
            }

            if ($field->type === 'string' && $field->length !== null) {
                $parts[] = sprintf('new Property(TextInput::MaxLength, %d)', $field->length);
            }

            if ($field->type === 'integer') {
                $parts[] = 'new Property(NumberInput::Integer, true)';
            }

            if ($field->default !== null && $field->default !== false) {
                $parts[] = sprintf('new Property(%s::Default, %s)', $class, var_export($field->default, true));
            }

            if ($field->type === 'enum' && $field->enum !== null) {
                $imports[] = 'Gcego\\Forms\\Configuration\\Options';
                $imports[] = $field->enum;
                $parts[] = sprintf('new Options(%s::class)', class_basename($field->enum));
            }

            $lines[] = sprintf('new %s(%s),', $class, implode(', ', $parts));
        }

        return ['code' => $this->wrap('Form', $lines, $indent), 'imports' => $imports];
    }

    /**
     * @param  list<ModelField>  $fields
     * @return array{code: string, imports: list<string>}
     */
    public function table(array $fields, int $indent = 2): array
    {
        $imports = ['Gcego\\Components\\Property', 'Gcego\\Tables\\Table'];
        $lines = [];
        $searchable = false;

        foreach ($fields as $field) {
            if ($field->type === 'text' || ! $this->safe($field->name) || count($lines) >= self::TableColumns) {
                continue;
            }

            [$class, $import] = match ($field->type) {
                'boolean' => ['Boolean', 'Gcego\\Tables\\Columns\\Boolean'],
                'date' => ['Date', 'Gcego\\Tables\\Columns\\Date'],
                'datetime' => ['DateTime', 'Gcego\\Tables\\Columns\\DateTime'],
                'integer', 'decimal' => ['Number', 'Gcego\\Tables\\Columns\\Number'],
                default => ['Text', 'Gcego\\Tables\\Columns\\Text'],
            };
            $imports[] = $import;
            $parts = [$this->quote($field->name), $this->quote($this->label($field->name))];

            if ($class === 'Text' && ! $searchable) {
                $parts[] = 'new Property(Text::Searchable, true)';
                $parts[] = 'new Property(Text::Sortable, true)';
                $searchable = true;
            }

            $lines[] = sprintf('new %s(%s),', $class, implode(', ', $parts));
        }

        if ($searchable) {
            $imports[] = 'Gcego\\Tables\\Configuration\\Search';
            array_unshift($lines, 'new Search,');
        }

        return ['code' => $this->wrap('Table', $lines, $indent), 'imports' => $imports];
    }

    /**
     * @param  list<ModelField>  $fields
     * @return array{code: string, imports: list<string>}
     */
    public function infolist(array $fields, int $indent = 2): array
    {
        $imports = ['Gcego\\Components\\Property', 'Gcego\\Infolists\\Infolist'];
        $lines = [];

        foreach ($fields as $field) {
            if (! $this->safe($field->name)) {
                continue;
            }

            [$class, $alias] = match ($field->type) {
                'boolean' => ['Boolean', 'BooleanEntry'],
                'date' => ['Date', 'DateEntry'],
                'datetime' => ['DateTime', 'DateTimeEntry'],
                'integer', 'decimal' => ['Number', 'NumberEntry'],
                default => ['Text', 'TextEntry'],
            };
            $imports[] = 'Gcego\\Infolists\\Entries\\'.$class.' as '.$alias;
            $parts = [$this->quote($field->name), $this->quote($this->label($field->name))];

            if ($field->type === 'text') {
                $parts[] = 'new Property(TextEntry::Multiline, true)';
            }

            $lines[] = sprintf('new %s(%s),', $alias, implode(', ', $parts));
        }

        return ['code' => $this->wrap('Infolist', $lines, $indent), 'imports' => $imports];
    }

    /**
     * "use" lines for $imports (deduplicated, sorted, "Property" only when used).
     *
     * @param  list<string>  $imports
     */
    public function imports(array $imports, string $code): string
    {
        $imports = array_values(array_unique($imports));
        $imports = array_values(array_filter($imports, static fn (string $import): bool => $import !== 'Gcego\\Components\\Property' || str_contains($code, 'new Property(')));
        usort($imports, static fn (string $a, string $b): int => strcasecmp(str_replace(' as ', ' ', $a), str_replace(' as ', ' ', $b)));

        return implode("\n", array_map(static fn (string $import): string => 'use '.$import.';', $imports));
    }

    public function label(string $name): string
    {
        return Str::headline($name);
    }

    /**
     * @param  list<string>  $lines
     */
    private function wrap(string $class, array $lines, int $indent): string
    {
        if ($lines === []) {
            return sprintf('new %s', $class);
        }

        $pad = str_repeat('    ', $indent);

        return sprintf("new %s(\n%s\n%s)", $class, implode("\n", array_map(static fn (string $line): string => $pad.$line, $lines)), str_repeat('    ', $indent - 1));
    }

    private function quote(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
    }

    private function safe(string $name): bool
    {
        return preg_match('/^[a-z_][a-z0-9_]{0,63}$/', $name) === 1;
    }
}
