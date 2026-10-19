<?php

declare(strict_types=1);

namespace Gcego\Infolists\Entries;

use Gcego\Components\ComponentDefinition;
use Gcego\Components\PropertyDefinition;
use Gcego\Components\Type;
use Gcego\Infolists\InfolistsConfig;
use Gcego\Infolists\Support\SafeHtml;
use Gcego\Tables\Support\Formats;

/**
 * Text, escaped by default:
 *
 *     new Text('name', 'Subject Name')
 *     new Text('code', new Property(Text::Copyable, true), new Property(Text::Monospace, true))
 *     new Text('notes', new Property(Text::Multiline, true))                 // keeps line breaks
 *     new Text('description', new Property(Text::Limit, 200))                // truncated, full text in the tooltip
 *     new Text('syllabus', new Property(Text::Markdown, true))               // Markdown, rendered and sanitized
 *     new Text('body', new Property(Text::Html, true))                       // user HTML, sanitized against an allow-list
 *
 * Markdown and Html never output stored markup as-is: raw HTML is stripped
 * (Markdown) or sanitized (both), so scripts, event attributes and unsafe
 * URLs never reach the page.
 */
class Text extends Entry
{
    public const string Multiline = 'multiline';

    public const string Limit = 'limit';

    public const string Markdown = 'markdown';

    public const string Html = 'html';

    public const string Monospace = 'monospace';

    public const string Prefix = 'prefix';

    public const string Suffix = 'suffix';

    public function view(): string
    {
        return 'gcego-infolists::entries.text';
    }

    public function display(mixed $value, mixed $record, InfolistsConfig $config): array
    {
        $display = parent::display($value, $record, $config);
        $text = is_string($display['text']) ? $display['text'] : null;

        if ($text !== null && $this->get(self::Markdown) === true) {
            return [...$display, 'html' => SafeHtml::markdown($text), 'copy' => false];
        }

        if ($text !== null && $this->get(self::Html) === true) {
            return [...$display, 'html' => SafeHtml::html($text), 'copy' => false];
        }

        $limit = $this->intProperty(self::Limit);
        $short = $text !== null && $limit !== null && mb_strlen($text) > $limit ? rtrim(mb_substr($text, 0, $limit)).'…' : $text;

        return [
            ...$display,
            'text' => $short,
            'full' => $text,
            'title' => $display['title'] ?? ($short !== $text || $this->get(self::Tooltip) === true ? $text : null),
            'html' => null,
            'multiline' => $this->get(self::Multiline) === true,
            'mono' => $this->get(self::Monospace) === true,
        ];
    }

    protected function format(mixed $value, mixed $record, InfolistsConfig $config): ?string
    {
        $text = Formats::text($value);

        return $text === null ? null : ($this->stringProperty(self::Prefix) ?? '').$text.($this->stringProperty(self::Suffix) ?? '');
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Multiline, Type::bool(), default: false),
            new PropertyDefinition(self::Limit, Type::int(), rule: static fn (mixed $value): bool => is_int($value) && $value >= 1 && $value <= 10000, ruleDescription: 'between 1 and 10000'),
            new PropertyDefinition(self::Markdown, Type::bool(), default: false),
            new PropertyDefinition(self::Html, Type::bool(), default: false),
            new PropertyDefinition(self::Monospace, Type::bool(), default: false),
            new PropertyDefinition(self::Prefix, Type::string()),
            new PropertyDefinition(self::Suffix, Type::string()),
        );
    }
}
