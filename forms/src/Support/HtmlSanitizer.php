<?php

declare(strict_types=1);

namespace Gcego\Forms\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as Sanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Server-side HTML sanitization (symfony/html-sanitizer): only the allowed
 * elements survive, links keep only href (http, https, mailto) and get
 * rel="noopener noreferrer"; scripts, event handlers, styles, iframes and
 * javascript: URLs never do.
 */
final class HtmlSanitizer
{
    /**
     * @param  list<string>  $elements
     */
    public static function sanitize(string $html, array $elements): string
    {
        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks()
            ->withMaxInputLength(1_000_000);

        foreach ($elements as $element) {
            $config = $config->allowElement($element, $element === 'a' ? ['href', 'title'] : []);
        }

        if (in_array('a', $elements, true)) {
            $config = $config->forceAttribute('a', 'rel', 'noopener noreferrer');
        }

        return trim((new Sanitizer($config))->sanitize($html));
    }
}
