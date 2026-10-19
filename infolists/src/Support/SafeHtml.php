<?php

declare(strict_types=1);

namespace Gcego\Infolists\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * The only path from stored rich text to markup: Markdown is rendered with
 * raw HTML stripped and unsafe links disallowed, then everything is
 * sanitized against an element allow-list (no scripts, styles, event
 * attributes or javascript:/data: URLs; links get rel="noopener noreferrer").
 */
final class SafeHtml
{
    public const array Elements = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'code', 'pre', 'a', 'hr', 'table', 'thead', 'tbody', 'tr', 'th', 'td'];

    public static function html(string $html): HtmlString
    {
        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks()
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withMaxInputLength(100_000);

        foreach (self::Elements as $element) {
            $config = $config->allowElement($element, $element === 'a' ? ['href', 'title'] : []);
        }

        return new HtmlString((new HtmlSanitizer($config))->sanitize($html));
    }

    public static function markdown(string $markdown): HtmlString
    {
        return self::html(Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false, 'max_nesting_level' => 20]));
    }
}
