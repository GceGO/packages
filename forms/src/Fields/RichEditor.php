<?php

declare(strict_types=1);

namespace Gcego\Forms\Fields;

use Gcego\Components\Schema\ComponentDefinition;
use Gcego\Components\Schema\PropertyDefinition;
use Gcego\Components\Schema\Type;
use Gcego\Forms\Contracts\EditorAdapter;
use Gcego\Forms\FormsConfig;
use Gcego\Forms\Support\HtmlSanitizer;

/**
 * Formatted text stored as sanitized HTML.
 *
 * No WYSIWYG editor is bundled: the default adapter is a plain textarea in
 * which HTML (or plain text) is entered. Whatever the adapter, the server
 * sanitizes the HTML with symfony/html-sanitizer to the allowed elements
 * (scripts, event handlers, styles and unsafe URLs are removed) before it is stored.
 */
class RichEditor extends Field
{
    /** Allowed elements (defaults: p, br, strong, em, u, s, h2-h4, ul, ol, li, blockquote, code, pre, a). */
    public const string Allowed = 'allowed';

    public const string MaxLength = 'max_length';

    /** class-string<EditorAdapter> */
    public const string Editor = 'editor';

    public const array DefaultElements = ['p', 'br', 'strong', 'em', 'u', 's', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'code', 'pre', 'a'];

    public function view(): string
    {
        return 'gcego-forms::fields.rich-editor';
    }

    /**
     * @return list<string>
     */
    public function allowedElements(): array
    {
        $allowed = $this->get(self::Allowed);

        return is_array($allowed) ? array_values(array_filter($allowed, is_string(...))) : self::DefaultElements;
    }

    /**
     * @return class-string<EditorAdapter>|null
     */
    public function editor(): ?string
    {
        $editor = $this->stringProperty(self::Editor);

        return $editor !== null && is_a($editor, EditorAdapter::class, true) ? $editor : null;
    }

    public function typeRules(): array
    {
        $max = $this->intProperty(self::MaxLength);

        return $max === null ? ['string'] : ['string', 'max:'.$max];
    }

    public function dehydrate(mixed $value, FormsConfig $config): mixed
    {
        return is_string($value) ? HtmlSanitizer::sanitize($value, $this->allowedElements()) : $value;
    }

    protected static function defineSchema(): ComponentDefinition
    {
        return parent::defineSchema()->withProperties(
            new PropertyDefinition(self::Allowed, Type::list(), rule: static fn (mixed $value): bool => is_array($value) && array_filter($value, static fn (mixed $element): bool => ! is_string($element) || ! in_array($element, [...self::DefaultElements, 'h1', 'h5', 'h6', 'hr', 'span', 'sub', 'sup'], true)) === [], ruleDescription: 'a list of safe elements'),
            new PropertyDefinition(self::MaxLength, Type::int()),
            new PropertyDefinition(self::Editor, Type::classString(EditorAdapter::class)),
        );
    }
}
